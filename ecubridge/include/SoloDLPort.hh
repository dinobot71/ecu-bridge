#ifndef SOLODLPORT_HH
#define SOLODLPORT_HH

#include "RS232Port.hh"

enum SoloDLChannelMax {SoloDLChannelMax=15};

/**
 *
 * Channel ids (in order) for the supported data channel
 * types
 *
 */

enum class AIMChannel {
  RPM           = 1,
  WHEELSPEED    = 5,
  OILPRESS      = 9,
  OILTEMP       = 13,
  WATERTEMP     = 17,
  FUELPRESS     = 21,
  BATTVOLT      = 33,
  THROTANG      = 45,
  MANIFPRESS    = 69,
  AIRCHARGETEMP = 97,
  EXHTEMP       = 101,
  LAMBDA        = 105,
  FUELTEMP      = 109,
  GEAR          = 113,
  ERRORFLAG     = 125,
};

enum class AIMFreq {
  RPM           = 10,
  WHEELSPEED    = 10,
  OILPRESS      = 5,
  OILTEMP       = 2,
  WATERTEMP     = 2,
  FUELPRESS     = 5,
  BATTVOLT      = 5,
  THROTANG      = 10,
  MANIFPRESS    = 10,
  AIRCHARGETEMP = 2,
  EXHTEMP       = 2,
  LAMBDA        = 10,
  FUELTEMP      = 2,
  GEAR          = 5,
  ERRORFLAG     = 2,
};

/**
 *
 * Channel sample rates (in Hz. and in order)
 *
 */

class SoloDLPort : public RS232Port {

  private:

    /**
     *
     * slot - this is just a counter to tell us
     * which *relative* slice of a second we are on,
     * its assumed we send samples every 100ms (10hz),
     * so if any of the channels need a lower frequency
     * we can just modulo divide to figure out if we
     * need to send a particualar channel during a
     * givne slot.  The draw back is that we can't
     * do sample rates like 6 or 8.  But we don't need
     * to, and in fact the AIM UART protocol calls
     * for sample rates of 10, 2, and 5.
     *
     */

    int slot;

    /**
     *
     * data - copy of the last data we sent
     *
     */

    unsigned int data[SoloDLChannelMax+1];

    /**
     *
     * factor - pre-calculate the divisor we
     * use to check how often to send a given
     * channel. (look at writeSamples() to see
     * how it gets used).
     *
     */

    int factor[SoloDLChannelMax+1];

    /**
     *
     * chanMap - preCalculate the channel
     * ids that SoloDL needs to see for each
     * of our data channels.
     *
     */

    unsigned short chanMap[SoloDLChannelMax+1];

  protected:

  public:

    /*
     * standard constructor, if you given valid parameters, they will
     * be passed to openPort() to construct and open at the same time.
     *
     */

    SoloDLPort(const string & path="") :
      RS232Port(path, "19200,8,N,1", false), slot(0) {

      setClassName("SoloDLPort");

      /* pre-calculate - sample rates */

      factor[0]  = 0;
      factor[1]  = 10 / (int)AIMFreq::RPM;
      factor[2]  = 10 / (int)AIMFreq::WHEELSPEED;
      factor[3]  = 10 / (int)AIMFreq::OILPRESS;
      factor[4]  = 10 / (int)AIMFreq::OILTEMP;
      factor[5]  = 10 / (int)AIMFreq::WATERTEMP;
      factor[6]  = 10 / (int)AIMFreq::FUELPRESS;
      factor[7]  = 10 / (int)AIMFreq::BATTVOLT;
      factor[8]  = 10 / (int)AIMFreq::THROTANG;
      factor[9]  = 10 / (int)AIMFreq::MANIFPRESS;
      factor[10] = 10 / (int)AIMFreq::AIRCHARGETEMP;
      factor[11] = 10 / (int)AIMFreq::EXHTEMP;
      factor[12] = 10 / (int)AIMFreq::LAMBDA;
      factor[13] = 10 / (int)AIMFreq::FUELTEMP;
      factor[14] = 10 / (int)AIMFreq::GEAR;
      factor[15] = 10 / (int)AIMFreq::ERRORFLAG;

      /* pre-calculate - channel ids */

      chanMap[0]  = 0;
      chanMap[1]  = (int)AIMChannel::RPM;
      chanMap[2]  = (int)AIMChannel::WHEELSPEED;
      chanMap[3]  = (int)AIMChannel::OILPRESS;
      chanMap[4]  = (int)AIMChannel::OILTEMP;
      chanMap[5]  = (int)AIMChannel::WATERTEMP;
      chanMap[6]  = (int)AIMChannel::FUELPRESS;
      chanMap[7]  = (int)AIMChannel::BATTVOLT;
      chanMap[8]  = (int)AIMChannel::THROTANG;
      chanMap[9]  = (int)AIMChannel::MANIFPRESS;
      chanMap[10] = (int)AIMChannel::AIRCHARGETEMP;
      chanMap[11] = (int)AIMChannel::EXHTEMP;
      chanMap[12] = (int)AIMChannel::LAMBDA;
      chanMap[13] = (int)AIMChannel::FUELTEMP;
      chanMap[14] = (int)AIMChannel::GEAR;
      chanMap[15] = (int)AIMChannel::ERRORFLAG;

      if(!isReady()) {

        /* there was a problem opening the port */

        error("Could not setup Solo DL port.");

      } else {

        info("Solo DL is ready.");
      }
    }

    SoloDLPort(const SoloDLPort & obj) {

      operator=(obj);

    }

    SoloDLPort &operator=(const SoloDLPort & obj) {

      RS232Port::operator=(obj);


      return *this;
    }

    /**
     *
     * writeSamples() - assuming data is ready to be
     * written out, write out whatever is in the samples
     * array.
     *
     * @param samples int array - the array of channel data.
     * This is the final output, no more processing is done.
     * It will be sent as is. The array is expected to be at
     * least SoloDLChannelMax + 1 in size, since the
     * channel data goes from 1 .. 15 (not 0 .. 14).
     *
     * @return bool - exactly false on error.
     *
     * NOTE: we assume (per AIM protocol) that this method
     * will be called with a frequency of 10hz (every 100ms).
     * If you don't call it with that frequency, you will
     * need to adjust the code below.
     *
     * NOTE: channels are assumed to be in this following
     * order from samples[1] .. samples[15]:
     *
     *
     *   AIMChannel::RPM
     *   AIMChannel::WHEELSPEED
     *   AIMChannel::OILPRESS
     *   AIMChannel::OILTEMP
     *   AIMChannel::WATERTEMP
     *   AIMChannel::FUELPRESS
     *   AIMChannel::BATTVOLT
     *   AIMChannel::THROTANG
     *   AIMChannel::MANIFPRESS
     *   AIMChannel::AIRCHARGETEMP
     *   AIMChannel::EXHTEMP
     *   AIMChannel::LAMBDA
     *   AIMChannel::FUELTEMP
     *   AIMChannel::GEAR
     *   AIMChannel::ERRORFLAG
     *
     */

    bool writeSamples(unsigned int *samples);

    /* standard destructor */

    virtual ~SoloDLPort(void) {

    }
};

#endif
