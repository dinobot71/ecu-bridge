#include "PortMapper.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  PortMapper pm;

  if(!pm.isReady()) {
    cout << "[FAIL] can not map the ports!" << endl;
    return 1;
  }

  return 0;
}
