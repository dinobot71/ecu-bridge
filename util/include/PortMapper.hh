#ifndef PORTMAPPER_HH
#define PORTMAPPER_HH

#include "Object.hh"

/**
 *
 * PortMapper - allows us to symbolically refer to serial
 * ports, all the details with figuring out if they are
 * even available and which ones map to which of our
 * attached devices will be handled here.  The outside
 * world will just ask for the DL32 device path...and
 * get it.
 *
 */

class PortMapper : public Object {

  private:

    map<Device, string> devicePaths;

    map<Device, int> cableOrder;

    map<string, Device> deviceEnums;

    /* private methods */

    /**
     *
     * findFT4232H() - if we are using an FTDI quad cable
     * then this is how to find the usb serial devices that get
     * mapped in from the USB port.
     *
     * When this call is done devicePaths and cableOrder will
     * be filled in.
     *
     */

    bool findFT4232H(vector<string> & devices);

  protected:

  public:

    /* standard constructor */

    PortMapper(void) : Object("PortMapper") {

      unReady();

      /*
       * using our configuration file we will try to
       * map expected USB/Serial ports to device paths.
       *
       */

      if(!configure()) {

        /* there was a problem! */

      }
    }


    PortMapper(const PortMapper & obj) {
      operator=(obj);
    }

    PortMapper &operator=(const PortMapper & obj) {

      Object::operator=(obj);

      devicePaths = obj.devicePaths;
      cableOrder  = obj.cableOrder;
      deviceEnums = obj.deviceEnums;

      return *this;
    }

    /**
     *
     * getDevice() - given a symbolic device name, get the
     * actual device path.  The path is determined by auto
     * detection and our configuration file so we can easily
     * re-assign devices to different cables.
     *
     */

    string getDevice(const Device & code) {

      if(devicePaths.count(code) == 0) {
        return "";
      }

      return devicePaths[code];
    }

    /**
     *
     * configure() - (re)configure the port mapping from the
     * config.ini file.  Any of our features that use our
     * configuration file must support the configure method so
     * we can do a "reload" on our daemon.
     *
     */

    bool configure(void);

    /* standard destructor */

    virtual ~PortMapper(void) {

    }
};


#endif
