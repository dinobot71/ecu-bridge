#include "SoloDLPort.hh"

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

bool SoloDLPort::writeSamples(unsigned int *samples) {

  if(!isReady()) {

    error("Can't write data, port not ready.");
    return false;
  }

  int fd = getHandle();

  /* tick to the next (100ms) slice of a second */

  slot = 1 + slot % 10;

  /*
   * scan through the channels, and send then only if
   * its time to send them.
   *
   */

  for(unsigned short chan=1; chan<=SoloDLChannelMax; chan++) {

    if((slot % factor[chan]) != 0) {

      /* not time to send this channel yet */

      samples[chan] = 0;
      continue;
    }

    /*
     * format a packet for this channel, encoding the proper
     * channel id as Solo DL expects
     *
     */

    unsigned int checksum = 0;
    char packet[5];

    packet[0] = chanMap[chan];
    packet[1] = 0xA3;
    packet[2] = samples[chan] >> 8;
    packet[3] = samples[chan] % 256;

    for(int j=0; j<4; j++) {
      checksum += packet[j];
    }

    checksum %= 256;
    packet[4] = (unsigned short)checksum;

    /* send it! */

    size_t n = write(fd, packet, 5);

    if(n < 0) {

      error("can't write data.");
      return false;

    } if(n != 5) {

      error("didn't write whole packet.");
      return false;
    }
  }

  /*
   * we've scanned through all channels and sent out
   * those that need to be sent per they're expected
   * sample frequency.
   *
   */

  return true;
}
