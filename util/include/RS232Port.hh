#ifndef RS232PORT_HH
#define RS232PORT_HH

#include "Object.hh"

/**
 *
 * RS232Port - this class handles the basics of working
 * with an RS-232 port.  Sub-classes will take care of
 * actual protocol.
 *
 */

class RS232Port : public Object {

  private:

    /**
     *
     * fd - handle to the serial port
     *
     */

    int fd;

    /**
     *
     * args - port initialization args
     *
     */

    string args;

    /**
     *
     * mode the open mode (read/write)
     *
     */

    string mode;

    /**
     *
     * oldOptions - the options on the port before
     * we messed with it.
     *
     */

    struct termios oldOptions;

    /**
     * options - the current options.
     *
     */

    struct termios options;

    /**
     *
     * device - the file path of the actual device
     *
     */

    string device;

    /**
     *
     * blocking - enabled if we are doing blocking operations
     * (read port is normally blocking read)
     *
     */

    bool blocking;

    /**
     *
     * read timeout in seconds
     *
     */

    int readTimeoutSeconds;

  protected:

  public:

    /*
     * standard constructor, if you given valid parameters, they will
     * be passed to openPort() to construct and open at the same time.
     *
     */

    RS232Port(const string & path="", const string & params="", bool doBlock=true) :
      Object("RS232Port"), fd(-1), args(""), mode(""), device(""), blocking(false) {

      memset(&oldOptions, 0, sizeof(struct termios));
      memset(&options, 0, sizeof(struct termios));

      /*
       * mark as not usable, they must successfully open the port
       * before it can be used.
       *
       */

      unReady();

      readTimeoutSeconds = 10;

      if(!path.empty()) {

        /* they want to immediately open a port */

        openPort(path,params,doBlock);
      }
    }

    RS232Port(const RS232Port & obj) {
      operator=(obj);
    }

    RS232Port &operator=(const RS232Port & obj) {

      Object::operator=(obj);

      fd         = obj.fd;
      args       = obj.args;
      mode       = obj.mode;
      oldOptions = obj.oldOptions;
      options    = obj.options;
      device     = obj.device;
      blocking   = obj.blocking;

      readTimeoutSeconds = obj.readTimeoutSeconds;

      return *this;
    }

    /**
     *
     * openPort() - given the serial port we want to connect to an open,
     * do the initial open and configuration, and make it ready for reading
     * or writing as appropriate.
     *
     * @param path string   - the path to the device (i.e. /dev/ttyUSB1)
     *
     * @param params string - the RS232 configuraiton string such as 19200,8,N,1
     *
     * @param doBlock bool  - switch for blocking, normally ports you just read
     * from are blocking.
     *
     * @return bool - return exactly false if there was problem.
     *
     * NOTE: we open both read and write style ports as "read/write", which
     * is kind of overkill but not harmful either; we don't implement fancy line
     * control, we just use RX/TX lines so once the port is open, its just
     * blasting out read() or write() calls.
     *
     */

    bool openPort(const string & path, const string & params, bool doBlock=true);

    /**
     *
     * closePort() - close the port we opened, and do any cleanup, restoring the
     * port to what it was before we touched it.
     *
     * @return bool - return exactly false if some kind of error.
     *
     */

    bool closePort(void);

    /**
     *
     * setReadTimeout() - set the time to wait for data when
     * reading (in seconds).
     *
     * @param int seconds - the time to wait
     *
     */

    void setReadTimeout(int seconds=10) {
      readTimeoutSeconds = seconds;
    }

    /**
     *
     * getReadTimeout() - fetch the read timeout in seconds.
     *
     */

    int getReadTimeout(void) {
      return readTimeoutSeconds;
    }

    /**
     *
     * getHandle() - fetch the file descriptor for the port
     *
     */

    int getHandle(void) {
      return fd;
    }

    /**
     *
     * getDevice() - fetch the device name
     *
     */

    const string & getDevice() {
      return device;
    }

    /**
     *
     * waitForBytes() - sit on the port and wait for data to arrive...
     * for as long as it takes. Don't eat the data when it gets here,
     * just report how much is ready.
     *
     * @param maxRetry int - the number of timeouts to allow before
     * we bail.
     *
     * @return int the number of bytes ready for reading.  -1 on error.
     *
     */

    int waitForBytes(int maxRetry=0);

    /* standard destructor */

    virtual ~RS232Port(void) {
      closePort();
    }




};



#endif
