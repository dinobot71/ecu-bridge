#include "Object.hh"

/* we have to allow EasyLogger to setup global vari8ables */

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  Object obj;

  obj.info("hello I'm an object!");

  return 0;
}
