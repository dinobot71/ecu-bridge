#define ELPP_DISABLE_DEFAULT_CRASH_HANDLING

#include "DataTapWriter.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* this will default to port 6100 */

  DataTapWriter port;

  if(!port.isReady()) {
    cout << "[FAIL] can not open broadcast port." << endl;
    return 1;
  }

  cout << "serving messages..." << endl;

  while(1) {

    if(!port.send("This is a message!")) {
      cout << "[FAIL] can't send a message." << endl;
      return 1;
    }

    /* wait 5 seconds to send the next one */

    sleep(5);
  }

  cout << "shutting down..." << endl;

  return 0;
}
