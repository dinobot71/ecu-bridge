#define ELPP_DISABLE_DEFAULT_CRASH_HANDLING

#include "ECUBridge.hh"

INITIALIZE_EASYLOGGINGPP

const char *pidFile = "/var/run/ecubridge.pid";

ECUBridge *bridge = NULL;

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

/**
 *
 * signalHandler() - our hook for picking up signals
 *
 */

void signalHandler(int sig) {

  switch(sig){

    case SIGHUP:
      {
        /*
         * reconfigure...this is a little aggressive, we should
         * really make this be a pending request that loop() then
         * handles.  Forcing it like this might have bad impacts
         * on local variables in loop().
         *
         */

        if(bridge != NULL) {
          LogManager::info("[signal] requesting configure...");
          bridge->configure();
        }
      }
      break;

    case SIGTERM:
      {
        /* end */

        if(bridge != NULL) {
          LogManager::info("[signal] requesting stop...");
          bridge->stop();
        }
      }
      break;
  }
}


/**
 *
 * unlockDaemon() - unlock the daemon. This will happen
 * automatically when the process exits.
 *
 */

void unlockDaemon(int fd) {

  lockf(fd, F_ULOCK, 0);

  close(fd);
}

int main(int argc, const char* argv[]) {

  /* only allow to run as root */

  string userid = "";
  if(!whoami(userid)) {
    cout << "[ecubridgemain] can not determine user." << endl;
    return 1;
  }

  if(userid != "root") {
    cout << "[ecubridgemain] You (" << userid << ") must run this program as root." << endl;
    return 1;
  }

  /* close open descriptors */

  {
    for (int i=getdtablesize();i>=0;--i) {
      close(i);
    }
  }

  /* repoint stdin, stdout, stderr to a harmless place */

  {
    int i=open("/dev/null",O_RDWR);
    dup(i);
    dup(i);
  }

  /* daemonize... */

  /* fork ... */

  pid_t i=fork();
  if (i<0) exit(1); /* fork error */
  if (i>0) exit(0); /* parent exits */

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[ecubridgemain] can not configure logging." << endl;
    return 1;
  }

  LogManager::info("[ecubridgemain] starting...");

  /* new session */

  setsid();

  /* jail file creation */

  umask(027);

  /* change folders to a harmless place */

  chdir("/var/tmp");

  int lock = lockDaemon();

  if(lock == -1) {
    LogManager::error("[ecubridgemain] can not get PID lock.");
    return 1;
  }

  /* setup signal handling */

  signal(SIGHUP,  signalHandler);
  signal(SIGTERM, signalHandler);

  /* start the bridge */

  bridge = new ECUBridge();

  if(!bridge->isReady()) {
    unlockDaemon(lock);
    LogManager::error("[ecubridgemain] can not start the bridge.");
    return 1;
  }

  /* execute */

  if(!bridge->loop()) {
    LogManager::error("[ecubridgemain][ERROR] encountered runtime problem: %v",bridge->getError());
  }

  LogManager::info("[ecubridgemain] shutting down...");

  delete bridge;
  bridge = NULL;

  unlockDaemon(lock);

  LogManager::info("[ecubridgemain] done.");

  return 0;
}
