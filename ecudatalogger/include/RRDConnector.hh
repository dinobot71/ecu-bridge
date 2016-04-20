#ifndef RRDCONNECTOR_HH
#define RRDCONNECTOR_HH

#include "Object.hh"

#include <stdio.h>
#include <stdlib.h>
#include <errno.h>
#include <string.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <time.h>

/**
 *
 * RRDConnector - this class is an adaptor that allows us to
 * easily chat with a local RRD daemon so we can log data without
 * have to worry about the details of RRD definitions and format.
 * We just want to provide our 15 channels of data...and this
 * adaptor should take care of the rest.
 *
 * Note that we aim to have RRD store upto the most recent 24hours
 * of data for each kind of data we log (raw, normal, output).  We
 * want to do this for 15 channels each, and a given channel can be
 * a 32bit unsigned integer.
 *
 * Basically that means 3 RRD files, each of which uses about 52MB.
 *
 * Also, RRD does not do sub-second resolution.  So, since we generally
 * sample at 10hz (100ms)...we will gather 10 values of input (for any
 * of raw, normal or output) and *then* send a single value to RRD
 * through our active RRD Daemon connection.
 *
 * Performance: we're using a daemon exactly so we don't have run RRD
 * command line processes all the time, and so that we minimize writes
 * to disk (Flash memory for us on the Raspberry).  This means the
 * data log files will be about 5min out of date (flushes happen about
 * every 5min), but this is fine, we want the data logs for analysis
 * *after* a race weekend.  Its not expected to be used live.
 *
 */

enum class RRDDataFile {
  RAW    = 1,
  NORMAL = 2,
  OUTPUT = 3
};

class RRDConnector : public Object {

  private:

    /**
     *
     * dataFiles - the paths to our data log (RRD) files
     *
     */

    map<RRDDataFile, string> dataFiles;

    /**
     *
     * fd - the socket descriptor
     *
     */

    int fd;

    /**
     *
     * where to connect to RRD
     *
     */

    const char *RRD_UNIX_SOCET = "/var/run/rrdcached.sock";

    /**
     *
     * where we store RRD files
     *
     */

    const char *RRD_BASE_FOLDER = "/var/lib/rrdcached/db";


    /**
     *
     * readLine() - helper to read a single line of output from
     * RRD.
     *
     * @param line string - we pass back the line we recieved.
     *
     * @return bool - exactly false on error.
     *
     */

    bool readLine(string & line);

    /**
     *
     * createDataFiles() - make sure the data files for the ECU data
     * logging are ready for use.  If they already exist, don't do
     * anything, otherwise create 'em.
     *
     * @return bool - exactly false on error.
     *
     */

    bool createDataFiles(void);

  protected:

  public:

    /* standard constructor */

    RRDConnector() : Object("RRDConnector"), fd(-1) {

      unReady();

      if(!configure()) {

        /* there was a problem! */

      }
    }

    RRDConnector(const RRDConnector & obj) {
      operator=(obj);
    }

    RRDConnector &operator=(const RRDConnector & obj) {

      Object::operator=(obj);


      return *this;
    }

    /**
     *
     * configure() - reset and do whatever we have to in
     * order to connect to the RRD Daemon, and be ready
     * to process commands.  (This may block you until
     * the connection is ready).
     *
     */

    bool configure(void);

    /**
     *
     * doCommand() - given a command string, send it over to RRD and then read
     * out the results and pass them back in 'output'.  This is synchronous, so
     * you will be blocked until we get a response from RRD.
     *
     * @param command string - the command you want to send (must have proper syntax)
     *
     * @param output vector - the lines of output from the RRD daemon.
     *
     * @param status string - we pass back the status of the first line of output.
     *
     * @return bool - exactly false on error.
     *
     */

    bool doCommand(const string & command, vector<string> & output, string & status);

    /**
     *
     * logData() - helper to send a vector of data we got from the ecubridge
     * to the appropriate RRD data log file.  The vector of data is similar
     * to what we see in the ecu bridge for raw, normal and output data taps,
     * and the vector has values at the positions 1..15 (just like the ecu
     * bridge).  THe value at 0 is ignored.  ANy values above 15 are ignored.
     *
     * @param kind enum - the kind of data vector (raw, normal, output).
     *
     * @param d vector - the data values to log, vector is set at positions
     * 1..15 (its *not* zero based).
     *
     * @return bool - exactly false on error.
     *
     */

    bool logData(RRDDataFile kind, vector<unsigned int> & d);

    /**
     *
     * ping() - check that we have a good connection to RRD.
     *
     * @return bool - exactly false on errror.
     *
     */

    bool ping(void);

    /* disconnect from the RRD daemon */

    void clear(void);

    /* standard destructor */

    virtual ~RRDConnector(void) {
      clear();
    }

};

#endif
