#ifndef USBCABLE_HH
#define USBCABLE_HH

#include "Object.hh"

#include <libudev.h>
#include <stdio.h>
#include <stdlib.h>
#include <locale.h>
#include <unistd.h>
#include <string.h>

/**
 *
 * USBCable - this class handles working with USB; we connect to
 * the serial ports through RS-232/USB converter cables, which
 * is very convenient, but we need to then handle add/remove of
 * USB devices.  What if the cable isn't plugged in when the
 * Raspberry is powered on? This class allows us to enable
 * communications at the appropriate times (i.e. the cable is
 * plugged in).
 *
 * To work with USB we use libudev; not well documented, but it
 * seems to work reliably and works well with select() which
 * is convenient for us, since our event loop in the ECU Bridge
 * is coded around select().
 *
 * You can find some docs here:
 *
 *   http://www.signal11.us/oss/udev/
 *   ftp://www.kernel.org/pub/linux/utils/kernel/hotplug/libudev/ch01.html
 *
 */

enum class USBEvent {
  ADD     = 1,
  REMOVE  = 2,
  CHANGE  = 3,
  MOVE    = 4,
  ONLINE  = 5,
  OFFLINE = 6,
  UNKNOWN = 7
};

class USBCable : public Object {

  private:

    /**
     *
     * the actual ids and strings for our FTDI quad RS-232 cable
     * converter.  Unfortunately libudev doesn't give us any
     * easy way to decode vendor/product ids into actual strings
     * that are human readable.  There is a big database over
     * here though:
     *
     *   http://www.linux-usb.org/usb-ids.html
     *
     * For the moment we only need to recognize one kind of
     * cable, so we just have some constants for the FTDI cable.
     *
     */

    const char *VENDOR_FT4232H_ID   = "0403";
    const char *VENDOR_FT4232H_STR  = "Future Technology Devices International, Ltd";
    const char *PRODUCT_FT4232H_ID  = "6011";
    const char *PRODUCT_FT4232H_STR = "FT4232H Quad HS USB-UART/FIFO IC";

    /**
     *
     * cabledetails - if the cable is plugged in, then this will be
     * filled in with appropriate details.
     *
     */

    struct cabledetails_t {

      string serial;
      string version;
      string manufacturer;
      string product;
      string removable;

    } cabledetails;

    bool connected;

    /**
     *
     * fd - the descriptor we can select() on for
     * USB events.
     *
     */

    int fd;

    /**
     *
     * how long to wait for USB events.
     *
     */

    int readTimeout;

    /* libudev data structures to talk to USB */

    struct udev *udev;
    struct udev_monitor *mon;

    /**
     *
     * findCable() - helper to detect if the cable is present or not
     *
     * @return bool exactly true if the cable is plugged in.
     *
     */

    bool findCable(void);

  protected:

  public:

    /* standard constructor */

    USBCable() : Object("USBCable"), udev(NULL), mon(NULL), fd(-1),
      connected(false), readTimeout(10) {

      unReady();

      if(!configure()) {

        /* there was a problem! */

      }
    }

    USBCable(const USBCable & obj) {
      operator=(obj);
    }

    USBCable &operator=(const USBCable & obj) {

      Object::operator=(obj);

      udev = obj.udev;
      mon  = obj.mon;
      fd   = obj.fd;

      return *this;
    }

    /**
     *
     * configure() - (re)configure, make self ready
     * if possible.
     *
     */

    bool configure(void);

    /**
     *
     * getEvent() - after waiting for USB events, one is
     * ready, go and get it, passing back the kind of
     * event that happened.  We don't need a lot of detail
     * its a cable, cable goes in, cable goes out.
     *
     * @param eventKind - an enum we pass back to indicate
     * the kind of event that occured.
     *
     * @return bool - exactly false on error.
     *
     */

    bool getEvent(USBEvent & eventKind);

    /**
     *
     * waitForEvents() - wait for something to happen
     * (USB connect, disconnect etc.)
     *
     */

    bool waitForEvents(int maxRetry=0);

    /**
     *
     * getHandle() - fetch the descriptor we can use with
     * select() to watch for USB events.
     *
     */

    int getHandle(void) {
      return fd;
    }

    /**
     *
     * isConnected() - test if the cable is connected,
     * this is based on the last called to configure().
     *
     * @return bool - exactly true if the cable is connected.
     *
     */

    bool isConnected(void) {
      return connected;
    }

    /**
     *
     * clear() - get ready to configure fresh, or shutdown.
     *
     */

    bool clear(void);

    /* standard destructor */

    virtual ~USBCable(void) {
      clear();
    }
};

#endif
