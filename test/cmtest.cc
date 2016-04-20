#include "ChannelManager.hh"

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  ChannelManager cm;

  if(!cm.isReady()) {
    cout << "[FAIL] can not configure channel manager: " << cm.getError() << endl;
    return 1;
  }

  unsigned int samples[16] = {
    0,
    2300,
    10,
    50,
    100,
    100,
    250,
    12,
    45,
    250,
    100,
    100,
    42,
    100,
    3,
    0
  };

  unsigned int normal[16], output[16];

  if(!cm.load(samples, normal, output)) {
    cout << "[FAIL] can not load data: " << cm.getError() << endl;
    return 1;
  }

  /* dump out what we mapped */

  char buf[1024];

  cout << "loaded data:" << endl;

  for(int i=1; i<=15; i++) {

    sprintf(buf, "%6d => %6d => %6d", samples[i], normal[i], output[i]);
    cout << buf << endl;
  }

  cout << "." << endl;

  return 0;
}
