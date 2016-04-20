#include "IniFile.hh"
#include "ConfigManager.hh"

int main(int argc, const char* argv[]) {

  cout << "[IniFile] testing..." << endl;

  IniFile ini = ConfigManager::instance("php-sample.ini");

  if(!ini.isReady()) {
    cout << "[FAIL] can not construct: " << ini.getError() << endl;
    return 1;
  }

  if(ini.enabled("PHP", "magic_quotes_gpc")) {
    cout << "[FAIL] magic_quotes_gpc is enabled, but shouldn't be." << endl;
    return 1;
  }
  if(!ini.enabled("PHP", "y2k_compliance")) {
    cout << "[FAIL] y2k_compliance is not enabled, but should be." << endl;
    return 1;
  }

  double value = ini.getDouble("MySQLi", "mysqli.default_port");
  if(value != 3306) {
    cout << "[FAIL] mysqli.default_port should be 3306 but got: " << value << endl;
    return 1;
  }

  /* all done */

  cout << "[OK] ." << endl;

  ConfigManager::clear();

  return 0;
}
