#include "DataTapReader.hh"

/**
 *
 * findIp() - helper to determine IP address of local IP4
 * interface.
 *
 */

bool DataTapReader::findIp(void) {

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

bool DataTapReader::configure(void) {

  if(isReady()) {

    /* if its already open, close it, we are force re-opening. */

    closePort();

  }

  /* create the socket */

  fd  = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
  if(fd < 0) {
    error(string("configure() - can't create UDP socket (") + to_string(port) + string("): ") + strerror(errno));
    return false;
  }

  /* go broadcast mode */

  int reuse = 1;

  if(setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, (char *)&reuse, sizeof(reuse)) != 0) {

    error(string("configure() - can't configure for reuse (") + to_string(port) + string("): ") + strerror(errno));
    return false;
  }

  /* figure out my own IP address */

  if(!findIp()) {

    /* no ip address ?! */

    return false;
  }

#ifndef __linux__
  memset(&addr, 0, sizeof(addr));
#endif

  addr.sin_family      = AF_INET;
  addr.sin_port        = htons(port);
  addr.sin_addr.s_addr = INADDR_ANY;

  /* bind to the port */

  if(bind(fd, (sockaddr *)&addr, sizeof(sockaddr)) != 0) {
    error(string("configure() - can't bind to port (") + to_string(port) + string("): ") + strerror(errno));
    return false;
  }

  /* join the multi-cast group */

  struct ip_mreq multipass;
  multipass.imr_multiaddr.s_addr = inet_addr(groupIp.c_str());
  multipass.imr_interface.s_addr = inet_addr(ip.c_str());

  if(setsockopt(fd, IPPROTO_IP, IP_ADD_MEMBERSHIP, (char *)&multipass, sizeof(multipass)) != 0) {
    error(string("configure() - can't join multi-cast group (") + groupIp + string("): ") + strerror(errno));
    return false;
  }

  /* ok we are ready to receive on it now... */

  info(string("configure() - listening on fd: ") + to_string(fd) + string(" Port: ") + to_string(port) + string(" IP Address: ") + ip + string(" Group: ") + groupIp);

  /* should be ready to send at this point */

  makeReady();

  /* all done */

  return true;
}

/**
 *
 * receive() - wait for the next message and when we get it,
 * pass it back in 'msg'.  We're using UDP so messages should
 * be kept small (on the order of 1K).  We allow for up 32K
 * messages here, but it should typically be a lot smaller.
 *
 * @param msg string - the message we got.
 *
 * @return bool - exactly false on any kind of error.
 *
 */

bool DataTapReader::receive(string & msg) {

  /*
   * leave room for 32K messages, we're using UDP so we will
   * almost certainly never get anything that big.
   *
   */

  static char buffer[1024*32];

  if(!isReady()) {
    error("receive() - can't receive (not listening)");
    return false;
  }

  /* grab the next message (blocking) */

  int n = recvfrom(fd, buffer, sizeof(buffer)-1, 0, NULL, NULL);

  if(n < 0) {
    error(string("receive() - failed to receive: ") + strerror(errno));
    return false;
  }

  /* hand back what we received */

  buffer[n] = '\0';
  msg       = "";

  msg.append(buffer);

  /* all done */

  return true;
}

/**
 *
 * closePort() - close the broadcast port and do any
 * cleanup.
 *
 * @return bool - exactly false if any kind of error.
 *
 */

bool DataTapReader::closePort(void) {

  if(!isReady()) {

    /* its not open */

    return false;
  }

  /* close it! */

  if(close(fd) != 0) {
    error(string("closePort() - can not close socket fd: ") + to_string(fd));
    return false;
  }

  unReady();

  fd = -1;
  ip = "";

#ifndef __linux__
  memset(&addr, 0, sizeof(addr));
  memset(&group, 0, sizeof(group));
#endif

  /* all done */

  return true;
}
