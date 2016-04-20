#include "RRDConnector.hh"

#include <string.h>

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure("ecudatalogger.ini")) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  RRDConnector rrd;

  if(!rrd.isReady()) {
    cout << "[FAIL] can not connect to RRD: " << rrd.getError() << endl;
    return 1;
  }

  vector<unsigned int> d;

  for(auto i=0; i<16; i++) {
    d.push_back(i);
  }

  if(!rrd.logData(RRDDataFile::RAW, d)) {
    cout << "[FAIL] can not log data: " << rrd.getError() << endl;
    return 1;
  }

  cout << "." << endl;

  return 0;
}
