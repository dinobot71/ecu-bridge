#ifndef LOG_MANAGER_HH
#define LOG_MANAGER_HH

#include <stdarg.h>

/* do stack tracking crashes (we compile with GCC so we can use this feature */

#define ELPP_STACKTRACE_ON_CRASH

#include "3rdparty/easylogging++.h"
#include "ConfigManager.hh"

/**
 *
 * LogManager - this is a thin wrapper for EasyLogging:
 *
 *   http://easylogging.muflihun.com/
 *
 * We want to use EasyLogging, but we want its use constrained
 * and pre-set per our own environment; essentially logging
 * will be routed through our own Object base class to EasyLogging,
 * and our LogManager class will handle the details of making
 * sure EasyLogging is properly configured and ready to use
 * based on our environment and our configuration system.
 *
 *
 */

class LogManager {

  private:

    /**
     *
     * rotateMax - the maximum number of log rotations
     * before falling off the end.
     *
     */

    static int rotateMax;

    /**
     *
     * logName - the path to the log file
     *
     */

    static string logName;

    /* we'll only have static methods */

    LogManager() {};
    LogManager(const LogManager &) {}
    LogManager &operator=(const LogManager &) {return *this;}

  protected:

  public:

    /**
     *
     * info() - standard INFO logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     */

    template<typename... Args> static void info(const char *format, Args... args) {
      instance()->info(format, args...);
    }

    /**
     *
     * debug() - standard DEBUG logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     */

    template<typename... Args> static void debug(const char *format, Args... args) {
      instance()->debug(format, args...);
    }

    /**
     *
     * warning() - standard WARN logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     */

    template<typename... Args> static void warning(const char *format, Args... args) {
      instance()->warn(format, args...);
    }

    /**
     *
     * error() - standard ERROR logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     */

    template<typename... Args> static void error(const char *format, Args... args) {
      instance()->error(format, args...);
    }

    /**
     *
     * fatal() - standard FATAL logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     * NOTE: be careful!  This will abort your program!
     *
     */

    template<typename... Args> static void fatal(const char *format, Args... args) {
      instance()->fatal(format, args...);
    }

    /**
     *
     * trace() - standard TRACE logger method.  Note: uses c++11 variadic
     * arguments, and you must use %v in the formate string to substitute
     * arguments. Its printf() "like", not exactly :)
     *
     */

    template<typename... Args> static void trace(const char *format, Args... args) {
      instance()->trace(format, args...);
    }

    /**
     *
     * instance() - fetch an instance of the log that we can use
     * to work with.  In theory you don't need to use this, just
     * use the provided info(), warn() etc methods.
     *
     */

    static el::Logger *instance(void) {

      el::Logger *defaultLogger = el::Loggers::getLogger("default");

      return defaultLogger;
    }

    /**
     *
     * rotationMax() - fetch the maximum number of log file
     * rotations to do.
     *
     */

    static int rotationMax(void) {
      return rotateMax;
    }

    /**
     *
     * configure() - (re)configure logging (done by EasyLogging)
     * based on our own configuration file.  Any of our features
     * that use our configuration file must support the configure
     * method so we can do a "reload" on our daemon.
     *
     */

    static bool configure(const string & fileName="/etc/ecubridge/ecubridge.ini");

    static string getFileName(void) {
      return logName;
    }

    /* standard constructor */

    ~LogManager() {

      /* do nothing */

    }

};


#endif
