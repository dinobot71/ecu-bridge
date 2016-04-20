#define ELPP_DISABLE_DEFAULT_CRASH_HANDLING

#include "CommandPort.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* this will default to port 6100 */

  CommandPort port;

  if(!port.isReady()) {
    cout << "[FAIL] can not open command port." << endl;
    return 1;
  }

  cout << "waiting for client..." << endl;

  if(!port.waitForClient()) {
    cout << "[FAIL] failed to wait: " << port.getError() << endl;
    return 1;
  }

  /* accept the new client */

  if(!port.accept()) {
    cout << "[FAIL] failed to accept: " << port.getError() << endl;
    return 1;
  }

  string command = "";

  /* read the command */

  cout << "reading command..." << endl;

  if(!port.receive(command)) {
    cout << "[FAIL] failed to read: " << port.getError() << endl;
    return 1;
  }

  cout << "Got Command: '" << command << "'." << endl;

  cout << "shutting down..." << endl;

  return 0;
}
