#ifndef DL32PORT_HH
#define DL32PORT_HH

#include "RS232Port.hh"

enum DL32ChannelMax {DL32ChannelMax=5};

class DL32Port : public RS232Port {

  private:

    /**
     *
     * payload - the input area for capturing bytes from the
     * DL-32.  We typically get a small package with
     * a few words of data, nothing so huge.
     *
     */

    char payload[2048];
    char buffer[256];

    /**
     *
     * data - the captured data, channels are in
     * order with channel 1 being data[1].
     *
     */

    unsigned int data[DL32ChannelMax+1];

  protected:

  public:

    /*
     * standard constructor, if you given valid parameters, they will
     * be passed to openPort() to construct and open at the same time.
     *
     */

    DL32Port(const string & path="") :
      RS232Port(path, "19200,8,N,1", true) {

      setClassName("DL32Port");

      if(!isReady()) {

        /* there was a problem opening the port */

        error("Could not setup DL-32 port.");

      } else {

        info("DL-32 is ready.");
      }
    }

    DL32Port(const DL32Port & obj) {

      operator=(obj);

    }

    DL32Port &operator=(const DL32Port & obj) {

      RS232Port::operator=(obj);

      return *this;
    }

    /**
     *
     * readSamples() - assuming data is ready to be
     * read, fetch the packet of data and return
     * the channel values in the given samples
     * array.
     *
     * @param samples int array - the array of
     * channel data.  Channels start at 1 and
     * go up to DL32ChannelMax, so samples must
     * be at least DL32ChannelMax+1 in size.
     *
     * @return bool - exactly false on error.
     *
     */

    bool readSamples(unsigned int *samples);

    /* standard destructor */

    virtual ~DL32Port(void) {

    }
};

#endif
