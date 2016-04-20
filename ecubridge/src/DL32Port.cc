#include "DL32Port.hh"

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

bool DL32Port::readSamples(unsigned int *samples) {

  int        fd = getHandle();
  size_t nReady = 0;
  size_t      n = 0;

  while(true) {

    buffer[0]     = 0x0;
    buffer[1]     = 0x0;

    /* wait for data to be ready */

    nReady = waitForBytes(fd);

    if(nReady <= 0) {
      error("waited but no bytes!!");
      return false;
    }

    /*
     * try to read off the first couple of bytes that
     * allow us to detect a packet header
     *
     */

    n = read(fd, buffer, 1);

    if(n < 1) {
      error("tried to read a byte, but couldn't.");
      return false;
    }

    if(((buffer[0] & 0xA2) == 0xA2)) {

      nReady = waitForBytes(fd);

      if(nReady <= 0) {
        error("waited but no bytes!!");
        return false;
      }

      n = read(fd, buffer + 1, 1);

      if(n < 1) {
        error("tried to read a byte, but couldn't.");
        return false;
      }

      if(((buffer[1] & 0x80) == 0x80)) {

        /* looks like a header! */

        break;

      } else {

        /* ignoring byte 1 */

      }

    } else {

      /* ignoring byte 0 */

    }
  }

  /* verify header */

  if(((buffer[0] & 0xA2) == 0xA2) && ((buffer[1] & 0x80) == 0x80)) {

    /*
     * get payload length (in 2 byte words), first byte last bit is B7
     * and B6..B0 of second byte are the remaining bits of the
     * one byte length value.
     *
     */

    size_t length = (buffer[0] & 0x01)*0x80 + (buffer[1] & 0x7F);
    size_t remain = length*2;
    size_t nRead  = 0;

    /*
     * read the payload, normally 'length' values, which are in
     * 2 bytes each (they are word values)
     *
     */

    while(nRead < (length*2)) {

      nReady = waitForBytes(fd);

      if(nReady <= 0) {
        error("waited but no bytes!!");
        return false;
      }

      n = read(fd, payload+nRead, remain);

      if(n == 0) {

        error("tried to read a byte, but couldn't.");
        return false;
      }

      if(n < 0) {
        error("can't read payload bytes!");
        return false;
      }

      nRead  += n;
      remain -= n;
    }

    /*
     * B15/B14 specify the kind of sub-packet:
     *
     *   1? - LM-1, B14 is recording or not recording
     *   01 - LC-1,
     *   00 - Other/Aux data source (12 Big DAC value), this is the DL-32 :)
     *
     */

    int wordNum = 0;

    for(int i=0; i<(length*2); i+=2) {

      if(((payload[i] & 0xC0) == 0) && ((payload[i+1] & 0x80) == 0)) {

        unsigned int word = (((unsigned short)payload[i]) << 8) + (unsigned short)payload[i+1];

        samples[wordNum+1] = word;
        data[wordNum+1]    = word; /* store a copy for later just in case */

        wordNum++;

      } else if(payload[i] & 0x80) {

        /* ignoring LM-1 sub-packet */

      } else {

        /* ignoring LC-1 sub-packet */

      }

    }

  } else {

    /* ignoring non-header */

  }

  /*
   * at this point all DL32ChannelMax channels should have been
   * read in, and 'samples' has the data.  We are done.
   *
   */

  return true;
}
