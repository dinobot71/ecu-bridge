#include "RS232Port.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  RS232Port port("/dev/ttyUSB0","19200,8,N,1",true);

  if(!port.isReady()) {
    cout << "[FAIL] can not open serial port." << endl;
    return 1;
  }






  return 0;
}
