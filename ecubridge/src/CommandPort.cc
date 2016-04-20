#include "CommandPort.hh"

/**
 *
 * findIp() - helper to determine IP address of local IP4
 * interface.
 *
 */

bool CommandPort::findIp(void) {

  ip = "";

  /*
   * we could call my_ip(ip);  ... but we won't. We're
   * going to use loop back (127.0.0.1) *always* because
   * the normal case is to not be connected to either
   * LAN or WIFI.  The raspberry will be on the dashboard
   * of a car on the track!
   *
   * So, we always use loop back.
   *
   */

  ip = "127.0.0.1";

  return true;
}

/**
 *
 * configure() - (re)configure, close the port if its
 * open and setup again.
 *
 */

bool CommandPort::configure(void) {

  info("configure() - Opening command port...");

  /* fresh start */

  if(isReady()) {

    /* if its already open, close it, we are force re-opening. */

    if(!closePort()) {
      return false;
    }

  }

  /* create the listen port */

  if((fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
    error(string("configure() - can not create listen socket: ") + strerror(errno));
    return false;
  }

  int yes = 1;
  if(setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &yes, sizeof(int)) == -1) {
    error(string("configure() - can not configure listen socket: ") + strerror(errno));
    return false;
  }

  /* bind */

  struct sockaddr_in addr;

#ifndef __linux__
  memset(&addr,  0, sizeof(addr));
#endif

  addr.sin_family      = AF_INET;
  addr.sin_addr.s_addr = INADDR_ANY;
  addr.sin_port        = htons(port);

  memset(&(addr.sin_zero), '\0', 8);

  if(bind(fd, (struct sockaddr *)&addr, sizeof(addr)) == -1) {
    error(string("configure() - can not bind socket to listen port: ") + strerror(errno));
    return false;
  }

  /*
   * mark it as a listen port, explicitly have a backlog of 1; we only
   * intend to ever have one "command" in flight at once, we aren't doing
   * threading etc.
   *
   */

  if(listen(fd, 1) == -1) {
    error(string("configure() - do listen mode on port: ") + strerror(errno));
    return false;
  }

  /* at this point we are ready to accept connections */

  info(string("configure() - command port (") + to_string(port) + string(") is open."));

  makeReady();

  /* all done */

  return true;
}

/**
 *
 * receive() - read exactly one line of input
 * from the client (the command they are sending us).
 * Lines are expected to be terminated by a '\n'. So
 * we will keep going until we see one.
 *
 * @param line string - the command line we are passing back.
 *
 * @return bool - exactly false on any error.
 *
 */

bool CommandPort::receive(string & line) {

  if(client < 0) {
    error("receive() - no client.");
    return false;
  }

  /*
   * we don't expect any line we read in as a command to ever
   * be bigger than 1K, but lets give 4K for good measure.
   *
   */

  static char buffer[4096];

  line = "";

  size_t  numRead;
  size_t  totRead;
  char   *buf=buffer;
  char    ch;

  totRead = 0;

  /*
   * to read a line, we bring it in character by character looking for a
   * line terminator.
   *
   */

  while(true) {

    /* read next character ... */

    numRead = read(client, &ch, 1);

    if (numRead == -1) {

      if (errno == EINTR) {
        continue;
      } else {
        error(string("receive() - problem reading line: ") + strerror(errno));
        return false;
      }

    } else if (numRead == 0) {

      /* end of input? */

      if (totRead == 0) {

        /* end of input, with no input, so return "" */

        return true;

      } else {

        /* line ended without terminator */

        break;
      }

    } else {

      if(totRead >= 4095) {

        /* buffer is full! */

        break;
      }

      /* if we get any embedded weird stuff, just ignore it */

      if(ch != '\n') {

        if((ch < 32) || (ch > 126)) {
          ch = ' ';
        }
      }

      *buf++ = ch;
      totRead++;

      /* end of line? Back up one char, so we don't include the \n */

      if(ch == '\n') {
        buf--;
        break;
      }
    }
  }

  *buf = '\0';
  line = buffer;

  /* all done */

  return true;
}

/**
 *
 * send() - send our response to the client, normally
 * expected to be a single line.  We force it to have
 * an additional '\n' on the end to terminate the
 * line response to the client.
 *
 * @param line string - the line of text to send.
 *
 * @return bool - exactly false on error.
 *
 */

bool CommandPort::send(const string & line) {

  if(client < 0) {
    error("send() - no client.");
    return false;
  }

  /* write the response text */

  size_t n = write(client, line.c_str(), line.size());

  if(n != line.size()) {
    error("send() - could not write entire line.");
    return false;
  }

  /* terminate the line */

  char buf[2];
  buf[0] = '\n';
  buf[1] = '\0';

  n = write(client, buf, 1);

  if(n != 1) {
    error("send() - could not terminate line.");
    return false;
  }

  /* all done */

  return true;
}

/**
 *
 * accept() - open connection to the waiting client. We
 * only take one client at a time.  After this call
 * completes you may use receive() and send().  Use
 * drop() to close the client connection.
 *
 * @return bool - exactly false on error.
 *
 */

bool CommandPort::accept(void) {

  if(!isReady()) {
    error("accept() - can not accept clients, not connected.");
    return false;
  }

  info("accept() - new connection ...");

  /* handle new connections */

  struct sockaddr_in addr;
  socklen_t len = sizeof(addr);

  client = ::accept(fd, (struct sockaddr *)&addr, &len);

  if(client < 0) {
    client = -1;
    error(string("accept() can not accept client: ") + strerror(errno));
    return false;
  }

  string clientIp = inet_ntoa(addr.sin_addr);

  info(string("accept() - client: ") + clientIp + string(":") + to_string(port) + string(" fd: ") + to_string(client));

  /* all done */

  return true;
}

/**
 *
 * drop() - if we have a client connected, then drop them.
 *
 * @return bool - exactly false on error.
 *
 */

bool CommandPort::drop(void) {

  if(client < 0) {

    /* nothing to do */

    return true;
  }

  info(string("drop() - dropping client (") + to_string(client) + string(")..."));

  send("END\n");
  close(client);

  client = -1;

  return true;
}

/**
 *
 * waitForClient() - wait for the next client to arrive,
 * and when they do, don't take any action, just return
 * successfully.  Call must use accept() to start a new
 * client.
 *
 */

bool CommandPort::waitForClient(int maxRetry) {

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

    int result = select(maxFD, &fdSet, (fd_set*)0, (fd_set*)0, &timeOut);

    if(result<0) {

      error(string("waitForBytes() - select error!"));
      return -1;

    } else if(result>0) {

      /* client has arrived! */

      break;

    } else {

      retryCount++;

      if(maxRetry > 0) {

        warning(string("waitForBytes() - select timed out (") + to_string(readTimeoutSeconds) + ").");

        if(retryCount >= retryCount) {
          error(string("waitForBytes() - maximum # of timeouts reached (") +to_string(retryCount) + ").");
          return -1;
        }
      }

      continue;
    }
  }

  /* all done */

  return true;
}

/**
 *
 * closePort() - close the command port and do any
 * cleanup.
 *
 * @return bool - exactly false if any kind of error.
 *
 */

bool CommandPort::closePort(void) {

  info("closePort() - closing...");

  if(!isReady()) {

    /* its not open */

    return false;
  }

  /* close it! */

  if(close(fd) != 0) {
    error(string("closePort() - can not close socket fd: ") + to_string(fd));
    return false;
  }

  /* if there is a client, drop them. */

  drop();

  unReady();

  fd      = -1;
  client  = -1;
  ip      = "";

  info("closePort() - closed.");

  /* all done */

  return true;
}
