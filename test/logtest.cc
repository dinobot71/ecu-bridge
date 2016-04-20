#include "LogManager.hh"

/* we have to allow EasyLogger to setup global vari8ables */

INITIALIZE_EASYLOGGINGPP

int main(int argc, const char* argv[]) {

  cout << "[LogManager testing..." << endl;

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* at this point we should be able to do logging */

  LogManager::error("this is a %v log %v message %v!", 9, "hello", 3.14);
  LogManager::warning("this is a log message!");
  LogManager::info("this is a log message!");
  LogManager::debug("this is a log message!");
  LogManager::trace("this is a log message!");

  cout << "all done." << endl;

  return 0;
}
