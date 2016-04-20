#include "RRDConnector.hh"
#include "DataTapReader.hh"
#include "SoloDLPort.hh"
#include <string.h>

INITIALIZE_EASYLOGGINGPP

const char *pidFile = "/var/run/ecudatalogger.pid";

bool extraDebug = false;

/**
 *
 * lockDaemon() try to lock the daemon and set the PID file
 * appropriately.
 *
 * @return - on success return the descriptor for the lock,
 * otherwise return -1.
 *
 * Note that the lock will automatically be released if the
 * process exits.
 *
 */

int lockDaemon(void) {

  int fd = open(pidFile, O_RDWR|O_CREAT, 0640);

  if(fd < 0) {

    /* can't open the PID file */

    return -1;
  }

  if(lockf(fd, F_TLOCK, 0)<0) {

    /* can't get lock */

    return -1;
  }

  /* only first instance continues */

  char str[64];

  sprintf(str,"%d\n",getpid());

  write(fd, str, strlen(str));

  return fd;
}

/**
 *
 * signalHandler() - our hook for picking up signals
 *
 */

void signalHandler(int sig) {

  switch(sig){

    case SIGHUP:
      {
        /* reconfigure, for now do nothing */

        LogManager::info("[ecudatalogger] received SIGHUP (ignoring).");
      }
      break;

    case SIGTERM:
      {
        /* end */

        LogManager::info("[ecudatalogger] received SIGTERM, exiting.");

        exit(0);
      }
      break;
  }
}

/**
 *
 * lineToData() - helper to convert a line of data from one of the
 * data tap monitors to actual numerical data, and placed properly
 * in [1]..[15] positions in our data output vector.
 *
 * @param line string - the line to convert
 *
 * @param data vector - our output data vector.
 *
 * @return bool - exactly false on error.
 *
 */

bool lineToData(const string & line, vector<unsigned int> & data) {

  if(line.size() == 0) {
    LogManager::error("[ecudatalogger] lineToData() empty line.");
    return false;
  }

  if(data.size() != 0) {
    LogManager::error("[ecudatalogger] lineToData() data vector isn't clear.");
    return false;
  }

  /*
   * we expect a line like:
   *
   *   1,0,2,0,3,0,4,0,5,0,6,0,7,0,8,0,9,0,5,0,11,0,12,0,13,0,14,0,15,0
   *
   * Which is "pairs" of channel # followed by the value.  Basically we
   * have to have 15 values, and we want to place them into [1]..[15]
   * in the data output.
   *
   * Pref-fill the array so its size isn't changing as we update it (i.e.
   * keep pointers sane).
   *
   */

  for(int zz=0; zz<16; zz++) {
    data.push_back(0);
  }

  if(extraDebug) {
    string msg = string("[ecudatalogger] lineToData scanning: ") + line;
    LogManager::info(msg.c_str());
  }

  sscanf(line.c_str(),
         "1,%u,2,%u,3,%u,4,%u,  5,%u,  6,%u,7,%u,8,%u,9,%u,10,%u,  11,%u,  12,%u,  13,%u,  14,%u,15,%u",
         &data[1],  &data[2],  &data[3],  &data[4],  &data[5],
         &data[6],  &data[7],  &data[8],  &data[9],  &data[10],
         &data[11], &data[12], &data[13], &data[14], &data[15]);

  if(extraDebug) {

    string line = "";

    for(auto item : data) {
      line += to_string(item) + ",";
    }

    string msg = string("[ecudatalogger] lineToData()  read data: ") + line;
    LogManager::info(msg.c_str());

  }

  /* all done */

  return true;
}

/**
 *
 * averageData() - helper function to average a group of data lines into
 * a single row.
 *
 * @param data vector of vectors = the group of row data
 *
 * @param average vector - the single row (averaged)
 *
 * @return bool - exactly false on error.
 *
 */

bool averageData(vector<vector<unsigned int> > & data, vector<unsigned int> & average) {

  if(data.size() < 10) {
    LogManager::error("[ecudatalogger] averageData() not enough data.");
    return false;
  }

  if(average.size() != 0) {
    LogManager::error("[ecudatalogger] averageData() average output not clear.");
    return false;
  }

  int N = data.size();

  for(int zz=0; zz<16; zz++) {
    average.push_back(0);
  }

  /* build the sum of each column */

  for(auto line : data) {

    /* each data line must be [1]..[15] */

    if(line.size() < 16) {
      LogManager::error("[ecudatalogger] averageData() data line too small.");
      return false;
    }

    /* accumulate */

    for(int i=1; i<=15; i++) {
      average[i] += line[i];
    }

  }

  /*
   * average the columns, we have to be careful here!  The ECU
   * bridge sends out data ever 100ms (10hz), but...it sends
   * out each channel with its own frequency...per the AIM
   * protocol.  Some channels are 10hz, some are 2 and some are
   * 5.  When a channel isn't present, its set to 0...so we
   * can sum everything we get...but at the end we have to
   * divide by the frequency of that channel...not necessarily
   * 10.
   *
   */

  float channelFreq[16] = {
    10.0,
    (float)AIMFreq::RPM,
    (float)AIMFreq::WHEELSPEED,
    (float)AIMFreq::OILPRESS,
    (float)AIMFreq::OILTEMP,
    (float)AIMFreq::WATERTEMP,
    (float)AIMFreq::FUELPRESS,
    (float)AIMFreq::BATTVOLT,
    (float)AIMFreq::THROTANG,
    (float)AIMFreq::MANIFPRESS,
    (float)AIMFreq::AIRCHARGETEMP,
    (float)AIMFreq::EXHTEMP,
    (float)AIMFreq::LAMBDA,
    (float)AIMFreq::FUELTEMP,
    (float)AIMFreq::GEAR,
    (float)AIMFreq::ERRORFLAG
  };

  for(int i=1; i<=15; i++) {
    average[i] = (unsigned int)((float)average[i] / channelFreq[i]);
  }

  /*
   * we have to make ERRORFLAG be whatever the most recent value was,
   * its not something we can average.
   *
   */

  vector<unsigned int> & lastRow = data[data.size()-1];
  average[15] = lastRow[lastRow.size()-1];

  if(extraDebug) {

    string line = "";

    for(auto item : average) {
      line += to_string(item) + ",";
    }

    string msg = string("[ecudatalogger] averageData() data: ") + line;
    LogManager::info(msg.c_str());
  }

  /* all done */

  return true;
}

int main(int argc, const char* argv[]) {

  /* only allow to run as root */

  string userid = "";
  if(!whoami(userid)) {
    cout << "[ecudatalogger]  can not determine user." << endl;
    return 1;
  }

  if(userid != "root") {
    cout << "[ecudatalogger]  You (" << userid << ") must run this program as root." << endl;
    return 1;
  }

  /* close open descriptors */

  {
    for (int i=getdtablesize();i>=0;--i) {
      close(i);
    }
  }

  /* repoint stdin, stdout, stderr to a harmless place */

  {
    int i=open("/dev/null",O_RDWR);
    dup(i);
    dup(i);
  }

  /* daemonize... */

  /* fork ... */

  pid_t i=fork();
  if (i<0) exit(1); /* fork error */
  if (i>0) exit(0); /* parent exits */
  /* configure logging */

  if(!LogManager::configure("/etc/ecubridge/ecudatalogger.ini")) {
    cout << "[ecudatalogger] can not configure logging." << endl;
    return 1;
  }

  LogManager::info("[ecudatalogger] configuring...");

  /* make sure there is only of data logger */

  int lock = lockDaemon();

  if(lock == -1) {
    LogManager::error("[ecudatalogger] can not get PID lock.");
    return 1;
  }

  /* setup signal handling */

  signal(SIGHUP,  signalHandler);
  signal(SIGTERM, signalHandler);

  /* our connector to RRD (the data log) */

  RRDConnector rrd;

  if(!rrd.isReady()) {
    string msg = string("[ecudatalogger] can not connect to RRD: ") + rrd.getError();
    LogManager::error(msg.c_str());
    return 1;
  }

  /* our multi-cast listeners (the ecu bridge) */

  DataTapReader rawPort(6100);
  DataTapReader normalPort(6101);
  DataTapReader outputPort(6102);

  if(!rawPort.isReady()) {
    string msg = string("[ecudatalogger] can not connect raw data tap: ") + rawPort.getError();
    LogManager::error(msg.c_str());
    return 1;
  }
  if(!normalPort.isReady()) {
    string msg = string("[ecudatalogger] can not connect normal data tap: ") + normalPort.getError();
    LogManager::error(msg.c_str());
    return 1;
  }
  if(!outputPort.isReady()) {
    string msg = string("[ecudatalogger] can not connect output data tap: ") + outputPort.getError();
    LogManager::error(msg.c_str());
    return 1;
  }

  /*
   * our main listen loop, we key off of the output data, becuase
   * we know the ecu bridge sends output data every 100ms (10Hz).  SO
   * basically as soon as we have 10 outputData items, we average
   * all the data in all the arrays, to get the "value" for 1sec.
   * we then send that to the RRD data log.  We have to average to
   * a second, because RRD doesn't support resolution below 1sec.
   *
   * Other data loggers were considered, but they have their own
   * failings.  RRD is pretty much the best option, even though its
   * only good to a second of resolution.
   *
   * NOTE: the ecu bridge sends out broadcasts for raw, normal and
   * output all at the same time.  So if we got somethign for output,
   * we have it for all three.
   *
   */

  vector<vector<unsigned int> > rawData;
  vector<vector<unsigned int> > normalData;
  vector<vector<unsigned int> > outputData;

  unsigned int rowsLogged = 0;

  LogManager::info("[ecudatalogger] listening for ecu data...");

  while(true) {

    static vector<unsigned int> d1;
    static vector<unsigned int> d2;
    static vector<unsigned int> d3;

    string rawLine;
    string normalLine;
    string outputLine;

    /* get the 'output' data */

    if(!outputPort.receive(outputLine)) {
      string msg = string("[ecudatalogger] bad output receive: ") + outputPort.getError();
      LogManager::error(msg.c_str());
      continue ;
    }

    /* get the 'normal' data */

    if(!normalPort.receive(normalLine)) {
      string msg = string("[ecudatalogger] bad normal receive: ") + normalPort.getError();
      LogManager::error(msg.c_str());
      continue ;
    }

    /* get the 'raw' data */

    if(!rawPort.receive(rawLine)) {
      string msg = string("[ecudatalogger] bad raw receive: ") + rawPort.getError();
      LogManager::error(msg.c_str());
      continue ;
    }

    /*
     * convert the text messages that were broadcast of UDP multi-cast
     * to actual numerical data we can log.
     *
     */

    d1.clear();
    if(!lineToData(outputLine, d1)) {
      LogManager::error("[ecudatalogger] can not convert output data.");
      continue;
    }

    d2.clear();
    if(!lineToData(normalLine, d2)) {
      LogManager::error("[ecudatalogger] can not convert normal data.");
      continue;
    }

    d3.clear();
    if(!lineToData(rawLine, d3)) {
      LogManager::error("[ecudatalogger] can not convert raw data.");
      continue;
    }

    /*
     * queue up the data, as soon as we have 10 (one second worth), we will
     * average them and log it.
     *
     */

    if(extraDebug) {
      LogManager::info("[ecudatalogger] queuing data...");
    }

    rawData.push_back(d3);
    normalData.push_back(d2);
    outputData.push_back(d1);

    if(outputData.size() < 10) {

      /* keep queuing data ... */

      continue;
    }

    /*
     * we have 1 second worth of data, average it, and log the result
     * of the average.
     *
     */

    if(extraDebug) {
      LogManager::info("[ecudatalogger] averaging 1sec of data...");
    }

    static vector<unsigned int> data;

    {
      data.clear();
      averageData(rawData, data);

      rrd.logData(RRDDataFile::RAW, data);

      rawData.clear();
    }

    {
      data.clear();
      averageData(normalData, data);

      rrd.logData(RRDDataFile::NORMAL, data);

      normalData.clear();
    }

    {
      data.clear();
      averageData(outputData, data);

      rrd.logData(RRDDataFile::OUTPUT, data);

      outputData.clear();
    }

    if(extraDebug) {
      LogManager::info("[ecudatalogger] data logged.");
    }

    /* all data has been logged, go wait for another 1 second batch... */

    rowsLogged++;

    if((rowsLogged % 300) == 0) {
      string msg = string("[ecudatalogger] 5min roll over, rows logged: ") + to_string(rowsLogged);
      LogManager::info(msg.c_str());
    }
  }

  LogManager::info("[ecudatalogger] finished.");

  return 0;
}
