#include "IniFile.hh"


IniFile::IniFile(const string & fileName) : ready(false), error("") {

  if(!parseFile(fileName)) {

    /* we could not parse the given configuration file */

    return ;
  }

  /* all done */

  ready = true;
}


/**
 * dump() - for debugging, dump to cout the configuration
 * as of now.
 *
 */

bool IniFile::dump() {

  cout << "[DEBUG] Dumping .ini file configuration:\n\n";

  for(auto const &section : sections) {

    cout << "[" << section.first << "]" << endl;

    for(auto const &pair : section.second) {

      cout << pair.first << " = " << pair.second << endl;

    }
  }

  cout << "\n[DEBUG] .\n\n" << endl;

  return true;
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

bool IniFile::enabled(const string & section, const string & name) {

  if(!isReady()) {
    return false;
  }

  string value = strtolower(getValue(section, name));

  regex  enabledRegex("1|(?:on)|t|(?:true)|(?:enabled)", regex_constants::ECMAScript|regex_constants::icase);
  smatch sm;

  if(regex_match(value, sm, enabledRegex)) {

    /* enabled! */

    return true;
  }

  /*
   * try to parse as a number, and if we get a non-zero value,
   * then consider it to be true.
   *
   */

  double number = 0.0;

  if(is_numeric(value)) {
    number = stod(value);
  }

  if(number != 0.0) {
    return true;
  }

  return false;
}

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

double IniFile::getDouble(const string & section, const string & name) {

  if(!isReady()) {
    return 0.0;
  }

  /* doesn't exist or is set to blank */

  string datum = getValue(section, name);

  if(datum.empty()) {
    return 0.0;
  }

  /* its not a number, just return 0.0 */

  if(!is_numeric(datum)) {
    return 0.0;
  }

  /* looks like a number, so do it! */

  return strtod(datum.c_str(), NULL);
}

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

const string & IniFile::getValue(const string & section, const string & name) {

  static string empty;

  if(!isReady()) {
    return empty;
  }

  /* we have to have a section and name to look up the value */

  if(section.empty() || name.empty()) {
    return empty;
  }

  return sections[section][name];
}

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

bool IniFile::setValue(const string & section, const string & name, const string & value) {

  if(section.empty() || name.empty()) {

    /* we have to have a section and name */

    return false;
  }

  /*
   * map<> template will insert default constructed values if
   * there isn't anything at that position yet.
   *
   */

  sections[section][name] = value;

  /* all done */

  return true;
}

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

bool IniFile::parseFile(const string & fileName) {

  string configFile = "";

  configFilePath = configFile;

  /*
   * To find it we try using hte given configuration file name on
   * the following paths:
   *
   *   <realpath of 'fileName'>
   *   <executable folder>
   *   <executable folder>/..
   *   <executable folder>/../..
   *   <executable folder>/../../..
   *   <cwd>
   *   <cwd>/..
   *   <cwd>/../..
   *   <cwd>/../../..
   *   /etc
   *
   * If it doesn't exist either directly, or as a file in
   * any of those folders, then we give up.
   *
   */

  string dir;
  string fName;
  string path;
  vector<string> searchFolders;

  if(dirname(fileName, dir)) {

    filename(fileName, fName);
    searchFolders.push_back(dir);

  } else {

    setError(string("Can't do dirname() on: ") + fileName);
    return false;
  }

  if(executable_path(path)) {

    string folder;

    if(dirname(path, folder)) {

      searchFolders.push_back(folder);
      searchFolders.push_back(folder + "/..");
      searchFolders.push_back(folder + "/../..");
      searchFolders.push_back(folder + "/../../..");
    }
  }

  if(getcwd(path)) {

    searchFolders.push_back(path);
    searchFolders.push_back(path + "/..");
    searchFolders.push_back(path + "/../..");
    searchFolders.push_back(path + "/../../..");
  }

  searchFolders.push_back("/etc");

  /*
   * if the file name they gave us is just an absolute
   * path (or relative to working directory) to the
   * file to use...then just use it.
   *
   */

  if(file_exists(fileName)) {

    configFile = fileName;

  } else {

    /*
     * look through the search path, looking for a hit on our
     * configuration file...
     *
     */

    for(int i=0; i<searchFolders.size(); i++) {

      string tmp = searchFolders[i] + "/";
      tmp += fName;

      if(file_exists(tmp)) {

        /* found it! */

        configFile = tmp;
        break;

      }
    }
  }

  /*
   * if we fall through all paths and we didn't get a hit
   * its time to give up.
   *
   */

  if(configFile.empty()) {
    setError(string("Can not find configuration file: ") + fileName);
    return false;
  }

  /*
   * parse! General loop is to look for sections and
   * to then look for <name> = <value> wihin a section.
   * ';' terminates input on any line (comments come
   * after).
   *
   */

  configFilePath = configFile;

  vector<string> rawLines;
  vector<string> lines;

  if(!file(configFile, rawLines)) {
    setError(string("Can't read input lines from file: ") + configFile);
    return false;
  }

  int lineNumber = 0;

  /*
   * first process the lines to strip off comments and
   * any leading/trailing whitespace.
   *
   */

  for(int i=0; i<rawLines.size(); i++) {

    string line = rawLines[i];

    /* trim off comment */

    const auto cmtBegin = line.find_first_of(";");

    if(cmtBegin != string::npos) {
      line = line.substr(0, cmtBegin);
    }

    /* trim whitespace */

    line = trim(line);

    if(line.empty()) {
      continue;
    }

    lines.push_back(line);
  }

  /* do we have any configuration to parse? */

  if(lines.empty()) {

    /* early exit */

    setError("No lines of configuration found.");
    return true;
  }

  /* walk the file... */

  string section;
  regex  sectionRegex("^\\[([a-zA-Z0-9._ \\-]+)\\]$", regex_constants::ECMAScript|regex_constants::icase);
  regex  valueRegex("^(\\S+)\\s*=\\s*(.+)$", regex_constants::ECMAScript|regex_constants::icase);
  regex  noValueRegex("^(\\S+)\\s*=\\s*$", regex_constants::ECMAScript|regex_constants::icase);

  smatch sm;

  while(lineNumber < lines.size()) {

    string line = lines[lineNumber];

    /* is this a section line? */

    if(regex_match(line, sm, sectionRegex)) {

      /* new section */

      section = sm[1];

    } else if(regex_match(line, sm, valueRegex)) {

      if(!section.empty()) {

        /* name = value line */

        string name  = trim(sm[1]);
        string value = trim(sm[2]);

        /* also get rid of any wrapping quotes if there are any */

        value = trim(value, "'\"");

        if(!setValue(section, name, value)) {

          /* some kind of internal error */

        }

      } else {

        /* we ignore anything that isn't in a section */

      }

    } else if(regex_match(line, sm, noValueRegex)) {

      /* they named the variable, but didn't give it a value */

      string name  = trim(sm[1]);
      string value = "";

      if(!setValue(section, name, value)) {

        /* some kind of internal error */

      }

    } else {

      /* unrecognizable line */


    }

    /* next line */

    lineNumber++;
  }

  /* debugging */

  if(false) {
    dump();
  }

  /* all done */

  return true;
}

IniFile::~IniFile(void) {

}
