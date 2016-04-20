#include "DL32Port.hh"
#include "PortMapper.hh"

#include <string.h>

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* find the device path */

  PortMapper pm;

  if(!pm.isReady()) {
    cout << "[FAIL] can not find USB/Serial Ports: " << pm.getError() << endl;
    return 1;
  }

  string device = pm.getDevice(Device::DL32);

  if(device.empty()) {
    cout << "[FAIL] can not find DL-32 device." << endl;
    return 1;
  }

  /* open it */

  DL32Port port(device);
  unsigned int samples[DL32ChannelMax+1];
  memset(samples, 0, sizeof(samples));

  if(!port.isReady()) {
    cout << "[FAIL] can not open DL-32: " << port.getError() << endl;
    return 1;
  }

  /*
   * read some data and exit
   *
   */

  int count = 200;
  char buf[1024];

  while(count > 0) {

    if(!port.readSamples(samples)) {
      cout << "[FAIL] can not sample: " << port.getError() << endl;
      return 1;
    }

    sprintf(buf, "%3d: %6d %6d %6d %6d %6d", count, samples[1], samples[2], samples[3], samples[4], samples[5]);

    cout << buf << endl;

    count--;
  }

  cout << "." << endl;

  return 0;
}
