#ifndef CONFIGMANAGER_HH
#define CONFIGMANAGER_HH

#include "IniFile.hh"

class ConfigManager {

  private:

    /*
     * this is a singleton factory, we only allow it to
     * return once instace per process.  SO only allow
     * the accessor method, no construction.
     *
     */

    ConfigManager() {};
    ConfigManager(const ConfigManager &) {}
    ConfigManager &operator=(const ConfigManager &) {return *this;}

    static IniFile *configFile;

  protected:

  public:

    /**
     *
     * instance() - fetch the one (and only) instance of a
     * IniFile for this program.  Its the "configuration"
     * (the big bag of settings for the application.  The
     * first call establishes the instance (via the given
     * file name), and after that we just return that same
     * instance.
     *
     * If you want to switch to a new config file location,
     * call clear() and then instance() again.
     *
     */

    static IniFile & instance(const string & fileName="/etc/ecubridge/ecubridge.ini");

    /* clear out the configuration singleton */

    static void clear() {

      if(configFile != NULL) {
        delete configFile;
        configFile = NULL;
      }
    }

    /* standard constructor */

    ~ConfigManager() {

      /* do nothing, its a singleton, call clear() to release the singleton. */

    }
};

#endif
