#include "LogManager.hh"

int LogManager::rotateMax  = 0;
string LogManager::logName = "";

/**
 *
 * logRolloutHandler() - callback hook used just before current
 * log file is deleted/truncated when it reaches max file size.
 *
 */

void logRolloutHandler(const char* logfile, size_t size) {

  int         N = LogManager::rotationMax();
  string folder = "";
  string file   = "";

  dirname(logfile, folder);
  filename(logfile, file);

  /* first rotate out all the old logs... */

  for(int i=(N-1); i>0; i--) {

    string src = folder + string("/") + file + string(".") + to_string(i);
    string dst = folder + string("/") + file + string(".") + to_string(i+1);

    /* does this one exist yet? */

    if(!file_exists(src)) {
      continue;
    }

    /* rotate it */

    if(rename(src.c_str(), dst.c_str()) != 0) {
      cerr << "ERROR: can not rotate log file (" << strerror(errno) << ") " << src << " to " << dst << endl;
      continue;
    }

  }

  /* move current log to log.1 ... */

  string src = folder + string("/") + file;
  string dst = folder + string("/") + file + ".1";

  if(rename(src.c_str(), dst.c_str()) != 0) {
    cerr << "ERROR: can not rotate log file (" << strerror(errno) << ") " << src << " to " << dst << endl;
    return ;
  }

  /* all done */
}

/**
 *
 * configure() - (re)configure logging (done by EasyLogging)
 * based on our own configuration file.  Any of our features
 * that use our cofiguraiton file must support the configure
 * method so we can do a "reload" on our daemon.
 *
 */

bool LogManager::configure(const string & fileName) {

  /* get the configuration settings */

  IniFile ini = ConfigManager::instance(fileName);

  if(!ini.isReady()) {

    /* we can't read/find the configuration file? */

    cerr << "ERROR: can not load configuration manager.  Missing config.ini file?" << endl;
    return false;
  }

  string format          = ini.getValue("Logging", "format");
  string filename        = ini.getValue("Logging", "filename");
  string level           = ini.getValue("Logging", "level");
  string tofile          = ini.getValue("Logging", "tofile");
  string tostdout        = ini.getValue("Logging", "tostdout");
  string max_file_size   = ini.getValue("Logging", "max_file_size");
  string flush_threshold = ini.getValue("Logging", "flush_threshold");
  string rotate          = ini.getValue("Logging", "rotate");

  /* we expect configuraiton for logging on the above key settings */

  if(format.empty() ||
     filename.empty() ||
     level.empty() ||
     tofile.empty() ||
     tostdout.empty() ||
     max_file_size.empty() ||
     flush_threshold.empty()) {
    cerr << "ERROR: configuration file (" << ini.configFileName() << ") is missing logging configuration settings." << endl;
    return false;
  }

  logName = filename;

  /* set the values */

  el::Configurations defaultConf;
  defaultConf.setToDefault();

  defaultConf.set(el::Level::Global, el::ConfigurationType::Format,            format);
  defaultConf.set(el::Level::Global, el::ConfigurationType::Filename,          filename);
  defaultConf.set(el::Level::Global, el::ConfigurationType::Enabled,           "false");
  defaultConf.set(el::Level::Global, el::ConfigurationType::ToFile,            tofile);
  defaultConf.set(el::Level::Global, el::ConfigurationType::ToStandardOutput,  tostdout);
  defaultConf.set(el::Level::Global, el::ConfigurationType::MaxLogFileSize,    max_file_size);
  defaultConf.set(el::Level::Global, el::ConfigurationType::LogFlushThreshold, flush_threshold);

  /* now enable the log level that has been configured */

  level = strtolower(level);

  if(level == "fatal") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
  } else if(level == "error") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Error,   el::ConfigurationType::Enabled, "true");
  } else if(level == "warning") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Error,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Warning, el::ConfigurationType::Enabled, "true");
  } else if(level == "info") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Error,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Warning, el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Info,    el::ConfigurationType::Enabled, "true");
  } else if(level == "debug") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Error,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Warning, el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Info,    el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Debug,   el::ConfigurationType::Enabled, "true");
  } else if(level == "trace") {
    defaultConf.set(el::Level::Fatal,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Error,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Warning, el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Info,    el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Debug,   el::ConfigurationType::Enabled, "true");
    defaultConf.set(el::Level::Trace,   el::ConfigurationType::Enabled, "true");
  } else {
    cerr << "ERROR: configuration file (" << ini.configFileName() << ") has a bad logging level value: " << level << endl;
    return false;
  }

  /* configure it */

  el::Loggers::reconfigureLogger("default", defaultConf);

  /* setup log rotation */

  rotateMax = 0;

  if(is_numeric(rotate)) {

    rotateMax = stoi(rotate);
  }

  if(rotateMax <= 0) {
    cerr << "ERROR: configuration file (" << ini.configFileName() << ") has a bad rotate value: " << rotateMax << endl;
    return false;
  }

  el::Loggers::addFlag(el::LoggingFlag::StrictLogFileSizeCheck);

  el::Helpers::installPreRollOutCallback(logRolloutHandler);

  /* all done */

  return true;
}
