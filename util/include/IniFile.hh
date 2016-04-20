#ifndef INIFILE_HH
#define INIFILE_HH

#include "util.hh"

#include <map>
#include <string>

class IniFile {

  private:

    /**
     *
     * ready - will be true if all goes well.
     *
     */

    bool ready;

    /**
     *
     * error - the last error we reported
     *
     */

    string error;

    /**
     *
     * configFilePath - the actual file that
     * we read in.
     *
     */

    string configFilePath;

    /**
     *
     * sections - a big hash map that lets us
     * drill down from sections to key/value
     * pairs.
     *
     */

    map<string, map<string, string> > sections;

    /**
     *
     * dump() - for debugging, dump to cout the configuration
     * as of now.
     *
     */

    bool dump();

  protected:

  public:

    /**
     *
     * Standard constructor, you can provide the
     * name/path to the initialization file (PHP
     * .ini like format), but we assume a reasonably
     * default, and will search *upwards* in the
     * directory tree for a match.
     *
     */

    IniFile(const string & fileName = "config.ini");

    /**
     *
     * configFileName - fetch the name of the configuration
     * file that we read in.
     *
     */

    const string & configFileName(void) {
      return configFilePath;
    }

    /**
     *
     * enabled() - for the given section and configuration setting
     * test if its value is something that is equivelant to "on".
     * Possible values:
     *
     *   1
     *   true
     *   On
     *   enabled
     *
     * If its not one of those we parse as a number, and if non-zero,
     * then its enabled.
     *
     * @param section string - the section of configuratioon details involved
     * @param name string - the name of the configuration item
     *
     * @return bool return true exactly if we can confirm its value
     * representing enabled. (turned on)
     *
     */

    bool enabled(const string & section, const string & name);

    /**
     *
     * getDouble() - for a given 'section' and configuration item 'name',
     * try to cast it as a number (a double).  If its not convertable, we
     * return 0.0, same if the value is blank/doesn't exist.
     *
     * @param section string - the section of configuratioon details involved
     * @param name string - the name of the configuration item
     *
     * @return double - the value converted to a number (is possible)
     *
     */

    double getDouble(const string & section, const string & name);

    /**
     * getValue() - in the given 'section' of configuration details, get the configuration
     * value of the item 'name'.  If it doesn't exist, we insert a blank value and return
     * that.
     *
     * @param section string - the section of configuratioon details involved
     * @param name string - the name of the configuration item
     *
     * @return string - the value for this configuration item.
     *
     */

    const string & getValue(const string & section, const string & name);

    /**
     * setValue() - in the given 'section' of configuration details, set the configuration
     * item 'name' to 'value'.
     *
     * @param section string - the section of configuratioon details involved
     * @param name string - the name of the configuration item
     * @param value string - the actual value (as a string)
     *
     * @return bool - return exactly false if there is a problem.
     *
     */

    bool setValue(const string & section, const string & name, const string & value);

    /**
     *
     * parseFile() - reset 'sections' to be a new
     * map of configuration settings from the given
     * .ini file.  We expect the format to be similar
     * to PHP style .ini files.  That is; has sections,
     * and comments are done with ';'...everything
     * after any ';' is ignored.
     *
     * NOTE: all settings must occur within a section.
     *
     */

    bool parseFile(const string & fileName);

    /**
     *
     * isReady() - check to see if we are ready for use
     * yet.
     *
     * @return bool return exactly false on any error.
     *
     */

    bool isReady(void) {
      return ready;
    }

    /**
     *
     * setError() - set the most recent error message
     * to the given message.
     *
     * @param msg string the new most recent error message.
     *
     */

    void setError(const string & msg) {
      error = msg;
    }

    /**
     *
     * getError() - get the most recent error message for
     * this object.
     *
     */

    const string & getError(void) {
      return error;
    }

    /* standard destructor */

    virtual ~IniFile(void);
};


#endif
