#ifndef DATATAPREADER_HH
#define DATATAPREADER_HH

#include "Object.hh"

#include <sys/types.h>
#include <ifaddrs.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <unistd.h>
#include <errno.h>
#include <netinet/in.h>
#include <netinet/ip.h>
#include <arpa/inet.h>

/**
 *
 * DataTapReader - we allow any client program or script
 * to monitor our ECU bridge daemon by just listening for
 * for messages on various "data tap" broadcast ports.  These
 * data taps are just UDP ports but they are in multi-cast
 * groups so that multiple clients can listen to the same
 * data tap at the same time.
 *
 * This allows the ECU bridge daemon to write out data for
 * others to monitor...and we don't care who or how many
 * there are.  "not our problem" :) It does mean though the
 * client have to be clever enough to join and listen to a
 * multi-cast group.  This class provides that ability, so
 * any client that at least uses this class...can listen in.
 *
 * For the IP address to use for multi-cast groups; we have
 * to use "class D" IP addresses:
 *
 *   https://en.wikipedia.org/wiki/Multicast_address
 *
 * and basically 226.* are free and clear (not used by anyone else)
 *
 * We reserved several UDP ports for this purpose (/etc/services):
 *
 *   6100, 6101, 6102, 6103, 6104
 *
 * Most likely only the first 3 will be use to watch data
 * flowing in from DL32, normal data in the bridge, and then
 * data sent to the SoloDL.
 *
 */

class DataTapReader : public Object {

  private:

    /**
     *
     * port - the port we listen to.
     *
     */

    uint16_t port;

    /**
     *
     * ip - my own IP address
     *
     */

    string ip;

    /**
     *
     * groupIp - this is the mutlicast group that clients will
     * receive our broadcasts on.
     *
     */

    string groupIp;

    /**
     *
     * addr - we cache the address structure we need to
     * use with recvfrom() so that we don't have to calculate
     * it each and every time. (we always listen to the same
     * broadcasting port.
     *
     */

    sockaddr_in addr;

    /**
     *
     * group - (multicast group) we cache the address structure we need to
     * use with sendto() so that we don't have to calculate
     * it each and every time. (we always broadcast to the
     * same place)
     *
     */

    sockaddr_in group;

    /**
     *
     * fd - the actual file descriptor for the broadcast
     * socket.
     *
     */

    int fd;

    /**
     *
     * findIp() - helper to determine IP address of local IP4
     * interface.
     *
     */

    bool findIp(void);

  protected:

  public:

    /*
     * standard constructor, you must provide the ip address
     * and port for the multi-cast group to join and listen
     * to messages from.
     *
     */

    DataTapReader(uint16_t bindPort=6100, const string & gip="226.1.1.1") :
      Object("DataTapReader"), port(bindPort), fd(-1), ip(""), groupIp(gip) {

      unReady();

      if(!configure()) {

        /* there was a problem! */

      }
    }

    DataTapReader(const DataTapReader & obj) {
      operator=(obj);
    }

    DataTapReader &operator=(const DataTapReader & obj) {

      Object::operator=(obj);

      port = obj.port;
      fd   = obj.fd;
      ip   = obj.ip;

      memcpy((void*)&addr, (void*)&obj.addr, sizeof(sockaddr_in));

      return *this;
    }

    /**
     *
     * configure() - (re)configure, close the port if its
     * open and setup again.
     *
     */

    bool configure(void);

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

    bool receive(string & msg);

    /**
     *
     * closePort() - close the broadcast port and do any
     * cleanup.
     *
     * @return bool - exactly false if any kind of error.
     *
     */

    bool closePort(void);

    /* standard destructor */

    virtual ~DataTapReader(void) {
      closePort();
    }


};

#endif
