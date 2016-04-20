#ifndef COMMANDPORT_HH
#define COMMANDPORT_HH

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
 * CommandPort - is our adaptor for accepting user commands
 * from a standard TCP style port.  This allows users to remotely
 * chat with the ECU Bridge daemon.  We reserved the port 5999
 * (/etc/services) for use by this adaptor.
 *
 */

class CommandPort : public Object {

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
     * fd - the actual file descriptor for the broadcast
     * socket.
     *
     */

    int fd;

    /**
     *
     * if we have a client, then this is the descriptor for
     * the actual client socket.
     *
     */

    int client;

    /**
     *
     * read timeout in seconds
     *
     */

    int readTimeoutSeconds;

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
     * standard constructor, you must provide the port to listen
     * on for commands.
     *
     */

    CommandPort(uint16_t bindPort=5999) :
      Object("CommandPort"), port(bindPort), fd(-1), client(-1), ip("") {

      unReady();

      readTimeoutSeconds = 10;

      if(!configure()) {

        /* there was a problem! */

      }
    }

    CommandPort(const CommandPort & obj) {
      operator=(obj);
    }

    CommandPort &operator=(const CommandPort & obj) {

      Object::operator=(obj);

      port   = obj.port;
      fd     = obj.fd;
      ip     = obj.ip;
      client = obj.client;

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
     * getHandle() - fetch the listen port descriptor.
     *
     * @return int the listen port descriptor
     *
     */

    int getHandle(void) {
      return fd;
    }

    /**
     *
     * accept() - open connection to the waiting client. We
     * only take one client at a time.  After this call
     * completes you may use receive() and send().  Use
     * drop() to close the client connection.
     *
     * @return bool - exactly false on error.
     *
     */

    bool accept(void);

    /**
     *
     * drop() - if we have a client connected, then drop them.
     *
     * @return bool - exactly false on error.
     *
     */

    bool drop(void);

    /**
     *
     * receive() - read exactly one line of input
     * from the client (the command they are sending us).
     * Lines are expected to be terminated by a '\n'. So
     * we will keep going until we see one.
     *
     * @param line string - the command line we are passing back.
     *
     * @return bool - exactly false on any error.
     *
     */

    bool receive(string & line);

    /**
     *
     * send() - send our response to the client, normally
     * expected to be a single line.  We force it to have
     * an additional '\n' on the end to terminate the
     * line response to the client.
     *
     * @param line string - the line of text to send.
     *
     * @return bool - exactly false on error.
     *
     */

    bool send(const string & line);

    /**
     *
     * waitForClient() - wait for the next client to arrive,
     * and when they do, don't take any action, just return
     * successfully.  Call must use accept() to start a new
     * client.
     *
     */

    bool waitForClient(int maxRetry=0);

    /**
     *
     * closePort() - close the command port and do any
     * cleanup.
     *
     * @return bool - exactly false if any kind of error.
     *
     */

    bool closePort(void);

    /* standard destructor */

    virtual ~CommandPort(void) {
      closePort();
    }
};

#endif
