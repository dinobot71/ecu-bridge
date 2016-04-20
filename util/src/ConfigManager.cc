#include "ConfigManager.hh"

IniFile *ConfigManager::configFile = NULL;

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

IniFile & ConfigManager::instance(const string & fileName) {

  /* if its already cached, pass it back */

  if(configFile != NULL) {
    return *configFile;
  }

  /* try to create one */

  configFile = new IniFile(fileName);

  if(!configFile->isReady()) {

    /*
     * something went wrong, missing config file etc.  We
     * leave it as is, so that the caller can call getError()
     * on the instance we created (but is bad).
     *
     */
  }

  return *configFile;
}
