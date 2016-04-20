#define ELPP_DISABLE_DEFAULT_CRASH_HANDLING

#include "DataTapReader.hh"

INITIALIZE_EASYLOGGINGPP

const char *pidFile = "/var/run/ecubridge.pid";

/**
 *
 * lockDaemon() try to lock the daemon and set the PID file
 * appropriately.
 *
 * @return - on success return the descriptor for the lock,
 * otherwise return -1.
 *
 * Note that the lock will automatically be released if the
 * process exits.
 *
 */

int lockDaemon(void) {

  int fd = open(pidFile, O_RDWR|O_CREAT, 0640);

  if(fd < 0) {

    /* can't open the PID file */

    return -1;
  }

  if(lockf(fd, F_TLOCK, 0)<0) {

    /* can't get lock */

    return -1;
  }

  /* only first instance continues */

  char str[64];

  sprintf(str,"%d\n",getpid());

  write(fd, str, strlen(str));

  return fd;
}

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* this will default to port 6100 */

  DataTapReader port;

  if(!port.isReady()) {
    cout << "[FAIL] can not open broadcast port." << endl;
    return 1;
  }

  while(1) {

    cout << "waiting for message..." << endl;

    string msg="";
    if(!port.receive(msg)) {
      cout << "[FAIL] can't receive a message." << endl;
      return 1;
    }

    cout << "Got message: " << msg << endl;

  }

  cout << "shutting down..." << endl;

  return 0;
}
