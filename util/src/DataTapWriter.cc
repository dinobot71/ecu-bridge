#include "DataTapWriter.hh"

/**
 *
 * findIp() - helper to determine IP address of local IP4
 * interface.
 *
 */

bool DataTapWriter::findIp(void) {

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

bool DataTapWriter::configure(void) {

  if(isReady()) {

    /* if its already open, close it, we are force re-opening. */

    if(!closePort()) {
      return false;
    }

  }

  /* create the socket */

  fd  = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
  if(fd < 0) {
    error(string("configure() - can't create UDP socket (") + to_string(port) + string("): ") + strerror(errno));
    return false;
  }

  /* go broadcast mode */

  int broadcast=1;

  /* determine my own IP address */

  if(!findIp()) {

    /* no ip address ?! */

    return false;
  }

#ifndef __linux__
  memset(&addr, 0, sizeof(addr));
#endif

  /* set the IP4 interface address we'll be using */

  addr.sin_family      = AF_INET;
  addr.sin_addr.s_addr = inet_addr(ip.c_str());

  /* finally we have to tell the socket to multi-cast via our IP */

  if(setsockopt(fd, IPPROTO_IP, IP_MULTICAST_IF, (char *)&addr.sin_addr.s_addr, sizeof(addr.sin_addr.s_addr)) < 0) {
    error("configure() - can't configure for multi-cast.");
    return false;
  }

  /*
   * set the multi-cast group, this is where we will multi-cast to, and
   * where clients will listen.
   *
   */

  memset((char *)&group, 0, sizeof(group));

  group.sin_family      = AF_INET;
  group.sin_addr.s_addr = inet_addr(groupIp.c_str());
  group.sin_port        = htons(port);

  info(string("configure() - ready, fd: ") + to_string(fd) + string(" Port: ") + to_string(port) + string(" IP Address: ") + ip + string(" Group: ") + groupIp);

  /* should be ready to send at this point */

  makeReady();

  /* all done */

  return true;
}

/**
 *
 * send() - broadcast a message.  Keep in mind
 * that UDP is being used (because we are broadcasting
 * blind to whoever wants to listent), so bigger
 * messages are more likely to be lost. In theory
 * UDP suports up to 64K size messages, but in reality
 * reliable messages are on the order of 1K in size.
 *
 * Because we are broadcasting only to ourselves,
 * not hopping over the net...it should be reliable
 * for a good range of messages sizes anyways.
 *
 * @param msg string - the text message to send.
 *
 * @return bool exactly false on any error.
 *
 */

bool DataTapWriter::send(const string & msg) {

  if(!isReady()) {
    error("send() - can't send port is not open.");
    return false;
  }

  /* send it! */

  int n = sendto(fd, msg.c_str(), msg.size(), 0, (struct sockaddr *)&group, sizeof(group));

  if((n >= 0) && (n < msg.size())) {
    error("send() - only sent part of the message!");
    return false;
  }

  if(n < 0) {
    error(string("send() - failed to send (") + msg + string("): ") + strerror(errno));
    return false;
  }

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

bool DataTapWriter::closePort(void) {

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

  fd      = -1;
  ip      = "";

#ifndef __linux__
  memset(&addr,  0, sizeof(addr));
  memset(&group, 0, sizeof(group));
#endif

  /* all done */

  return true;
}
