#ifndef DATATAPWRITER_HH
#define DATATAPWRITER_HH

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
 * DataTapWriter - to allow our daemon to openly
 * broadcast data to other processes, that may
 * be interested in various data, but don't have
 * to actually interact with our daemon our data
 * devices...we use UDP to broadcast out data
 * as need.  Whoever wants to listen...can listen.
 *
 * So DataTapWriter is the base class for a UDP
 * broadcaster.  Our daemon may actually use several
 * of these for providing data taps at various points
 * in the ECU bridge.
 *
 */

class DataTapWriter : public Object {

  private:

    /**
     *
     * port - the port we are broadcasting on.
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
     * addr - (local interface) we cache the address structure we need to
     * use with sendto() so that we don't have to calculate
     * it each and every time. (we always broadcast to the
     * same place)
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

    /**
     *
     * standard constructor, you must provide the multi-cast group IP
     * address and port that data will be broadcast on. Possible IP
     * addresses for multi-casting (class "D" addresses) are listed here:
     *
     *   https://en.wikipedia.org/wiki/Multicast_address
     *
     * But basically 226.* isn't reserved for anything.
     *
     * We've reserved (/etc/services) the following UDP broadcast
     * ports for use with our chump car bridge:
     *
     *    6100, 6101, 6102, 6103 and 6104
     *
     * Likely only need 3 of them; one to monitor DL32 input,
     * once to monitor SoloDL output, and one to monitor the normalized
     * data in the middle.
     *
     */

    DataTapWriter(uint16_t bindPort=6100, const string & gip="226.1.1.1") :
      Object("DataTapWriter"), port(bindPort), fd(-1), ip(""), groupIp(gip) {

      unReady();

      if(!configure()) {

        /* there was a problem! */

      }
    }

    DataTapWriter(const DataTapWriter & obj) {
      operator=(obj);
    }

    DataTapWriter &operator=(const DataTapWriter & obj) {

      Object::operator=(obj);

      port = obj.port;
      fd   = obj.fd;
      ip   = obj.ip;

      memcpy((void*)&addr,  (void*)&obj.addr,  sizeof(sockaddr_in));
      memcpy((void*)&group, (void*)&obj.group, sizeof(sockaddr_in));

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

    bool send(const string & msg);

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

    virtual ~DataTapWriter(void) {
      closePort();
    }


};

#endif
