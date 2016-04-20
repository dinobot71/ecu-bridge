#define ELPP_DISABLE_DEFAULT_CRASH_HANDLING

#include "USBCable.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* this will default to port 6100 */

  USBCable cable;

  if(!cable.isReady()) {
    cout << "[FAIL] can not find cable." << endl;
    return 1;
  }

  /* watch SUB events */

  while(1) {

    if(!cable.waitForEvents()) {
      cout << "[FAIL] can't watch events" << cable.getError() << endl;
      return 1;
    }

    USBEvent kind = USBEvent::UNKNOWN;

    if(!cable.getEvent(kind)) {

      cout << "[FAIL] can't decode event" << cable.getError() << endl;
      return 1;
    }

    if(cable.isConnected()) {
      cout << " *** CABLE CONNECTED *** " << endl;
    } else {
      cout << " *** DISCONNECTED *** " << endl;
    }

    /* keep watching */
  }

  cout << "shutting down..." << endl;

  return 0;
}
