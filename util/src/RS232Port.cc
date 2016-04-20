#include "RS232Port.hh"


/**
 *
 * openPort() - given the serial port we want to connect to an open,
 * do the initial open and configuration, and make it ready for reading
 * or writing as appropriate.
 *
 * @param path string   - the path to the device (i.e. /dev/ttyUSB1)
 *
 * @param params string - the RS232 configuraiton string such as 19200,8,N,1
 *
 * @param doBlock bool  - switch for blocking, normally ports you just read
 * from are blocking.
 *
 * @return bool - return exactly false if there was problem.
 *
 * NOTE: we open both read and write style ports as "read/write", which
 * is kind of overkill but not harmful either; we don't implement fancy line
 * control, we just use RX/TX lines so once the port is open, its just
 * blasting out read() or write() calls.
 *
 */

bool RS232Port::openPort(const string & path, const string & params, bool doBlock) {

  info(string("openPort() opening port: ") + path + string("..."));

  /* check parameters, make sure we arne't already open */

  if(isReady()) {

    /* already open */

    error("openPort() - Port is already open, you must close it first.");
    return false;
  }

  if(!file_exists(path)) {
    error(string("openPort() - No such port: ") + path);
    return false;
  }

  int tmpFd = open(path.c_str(), O_RDWR | O_NOCTTY);

  if(tmpFd < 0) {
    error(string("openPort() - can not open port: ") + path);
    return false;
  }

  /* do we need to do blocking operations? */

  if(doBlock) {

    if(fcntl(tmpFd, F_SETFL, 0) != 0) {
      close(tmpFd);
      error(string("openPort() - can not make port blocking: ") + path);
      return false;
    }

  }

  /* figure out the basic parameters, format is like: 19200,8,N,1 */

  struct termios newOptions;
  vector<string> arguments;

  explode(params, ",", arguments);

  if(tcgetattr(tmpFd, &newOptions) != 0) {
    close(tmpFd);
    error(string("openPort() - can not get options for port: ") + path);
    return false;
  }

  if(arguments.size() < 4) {
    close(tmpFd);
    error(string("openPort() - not enough RS-232 parameters: ") + params);
    return false;
  }

  /* save the old options */

  oldOptions = newOptions;

  /* pull out baud rate */

  string baudStr = arguments[0];
  speed_t baud;

  if(baudStr == "50") {
    baud = B0;
  } else if(baudStr == "75") {
    baud = B75;
  } else if(baudStr == "110") {
    baud = B110;
  } else if(baudStr == "134") {
    baud = B134;
  } else if(baudStr == "150") {
    baud = B150;
  } else if(baudStr == "200") {
    baud = B200;
  } else if(baudStr == "300") {
    baud = B300;
  } else if(baudStr == "600") {
    baud = B600;
  } else if(baudStr == "1200") {
    baud = B1200;
  } else if(baudStr == "1800") {
    baud = B1800;
  } else if(baudStr == "2400") {
    baud = B2400;
  } else if(baudStr == "4800") {
    baud = B4800;
  } else if(baudStr == "9600") {
    baud = B9600;
  } else if(baudStr == "19200") {
    baud = B19200;
  } else if(baudStr == "38400") {
    baud = B38400;
  } else if(baudStr == "57600") {
    baud = B57600;
  } else if(baudStr == "115200") {
    baud = B115200;
  } else if(baudStr == "230400") {
    baud = B230400;
  } else {
    close(tmpFd);
    error(string("openPort() - bad baud rate: ") + baudStr);
    return false;
  }

  cfsetispeed(&newOptions, baud);
  cfsetospeed(&newOptions, baud);

  /* pull out 8 v.s. 7 bit */

  if(arguments[1] == "8") {

    newOptions.c_cflag &= ~CSIZE;
    newOptions.c_cflag |= CS8;

  } else if(arguments[1] == "7") {

    newOptions.c_cflag &= ~CSIZE;
    newOptions.c_cflag |= CS7;

  } else if(arguments[1] == "6") {

    newOptions.c_cflag &= ~CSIZE;
    newOptions.c_cflag |= CS6;

  } else if(arguments[1] == "5") {

    newOptions.c_cflag &= ~CSIZE;
    newOptions.c_cflag |= CS5;

  } else {

    close(tmpFd);
    error(string("openPort() - bad character size: ") + arguments[1]);
    return false;
  }

  /* pull out parity/no parity */

  if(arguments[2] == "E") {

    /* even parity */

    newOptions.c_cflag |= PARENB;
    newOptions.c_cflag &= ~PARODD;

  } else if(arguments[2] == "O") {

    /* odd parity */

    newOptions.c_cflag |= PARENB;
    newOptions.c_cflag |= PARODD;

  } else if(arguments[2] == "N") {

    /* no parity */

    newOptions.c_cflag &= ~PARENB;

  } else {

    close(tmpFd);
    error(string("openPort() - bad parity: ") + arguments[2]);
    return false;
  }

  /* pull out # of stop bits */

  if(arguments[3] == "1") {

    newOptions.c_cflag &= ~CSTOPB;

  } else if(arguments[3] == "2") {

    newOptions.c_cflag |= CSTOPB;

  } else {

    close(tmpFd);
    error(string("openPort() - bad stop bit: ") + arguments[3]);
    return false;
  }

  /* take control of the port and allow reading */

  newOptions.c_cflag |= (CLOCAL | CREAD);

  /*
   * configure for raw mode with no software control, we
   * are just using the plain old RX/TX lines.
   *
   */

  newOptions.c_cflag &= ~CRTSCTS;
  newOptions.c_lflag  = 0; /* RAW input */
  newOptions.c_iflag  = 0; /* SW flow control off, no parity checks etc */
  newOptions.c_oflag &= ~OPOST;

  /* configure the port */

  if(tcflush(tmpFd, TCIFLUSH)) {
    close(tmpFd);
    error(string("openPort() - can not flush port: ") + path);
    return false;
  }

  if(tcsetattr(tmpFd, TCSANOW, &newOptions) != 0) {
    close(tmpFd);
    error(string("openPort() - can not set options on port: ") + path);
    return false;
  }

  /*
   * everything looks good, we should be good to go.
   *
   */

  options  = newOptions;
  fd       = tmpFd;
  device   = path;
  args     = params;
  mode     = "read/write";
  blocking = doBlock;

  makeReady();

  info(string("openPort() opened ") + device + string(" with ") + args + string(" fd: ") + to_string(fd) + string(" blocking: ") + to_string(blocking));

  /* all done */

  return true;
}

/**
 *
 * closePort() - close the port we opened, and do any cleanup, restoring the
 * port to what it was before we touched it.
 *
 * @return bool - return exactly false if some kind of error.
 *
 */

bool RS232Port::closePort(void) {

  info(string("closePort() - Closing port (") + device + string(" fd: ") + to_string(fd));

  if(!isReady()) {

    /* its not open */

    warning("closePort() - port isn't open.");
    return true;
  }

  /* go to unready state */

  unReady();

  /* restore previous options on port */

  if(tcsetattr(fd, TCSANOW, &oldOptions) != 0) {
    error("closePort() - Can't restore old options.");
  }

  /* close it */

  if(close(fd) != 0) {
    error(string("closePort() - Can't close port fd: ") + to_string(fd));
  }

  fd       = -1;
  args     = "";
  mode     = "";
  device   = "";
  blocking = false;

  memset(&oldOptions, 0, sizeof(struct termios));
  memset(&options, 0, sizeof(struct termios));

  info("closePort() - closed.");

  /* all done */

  return true;
}

/**
 *
 * waitForBytes() - sit on the port and wait for data to arrive...
 * for as long as it takes. Don't eat the data when it gets here,
 * just report how much is ready.
 *
 * @param maxRetry int - the number of timeouts to allow before
 * we bail.
 *
 * @return int the number of bytes ready for reading.  -1 on error.
 *
 */

int RS232Port::waitForBytes(int maxRetry) {

  struct timeval timeOut;
  fd_set fdSet;

  int maxFD      = 0;
  int retryCount = 0;

  while(true) {

    /* set the timeout */

    timeOut.tv_sec  = readTimeoutSeconds;
    timeOut.tv_usec = 0;

    FD_ZERO(&fdSet);
    FD_SET(fd, &fdSet);
    maxFD=fd+1;

    int result=select(maxFD, &fdSet, (fd_set*)0, (fd_set*)0, &timeOut);

    if(result<0) {

      error(string("waitForBytes() - select error!"));
      return -1;

    } else if(result>0) {

      /* data is ready! */

      break;

    } else {

      retryCount++;

      if(maxRetry > 0) {

        warning(string("waitForBytes() - select timed out (") + to_string(readTimeoutSeconds) + ").");

        if(retryCount >= maxRetry) {
          error(string("waitForBytes() - maximum # of timeouts reached (") +to_string(retryCount) + ").");
          return -1;
        }
      }

      continue;
    }
  }

  int available=0;

  if(ioctl(fd, FIONREAD, &available)!=0) {
    error(string("waitForBytes() - can't determine available byte count."));
    return -1;
  }

  return available;
}



