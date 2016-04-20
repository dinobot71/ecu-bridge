#ifndef ECUBRIDGE_HH
#define ECUBRIDGE_HH

#include "Object.hh"
#include "ChannelManager.hh"
#include "DL32Port.hh"
#include "SoloDLPort.hh"
#include "PortMapper.hh"
#include "DataTapWriter.hh"
#include "CommandPort.hh"
#include "USBCable.hh"

/**
 *
 * ECUBridge - this is the main controller for our daemon.
 * It will run continuously (as a daemon) and take care of
 * pass live data from the DL32 RS-232 port over to any
 * other ports we need to bridge to...such as the SoloDL
 * RS-232 port.
 *
 * The DL32 is just an analog to digital converter with a
 * few channels.  Its being used to convert direct analog
 * outputs from a Honda ECU to channels of data we can then
 * map into a SoloDL.  The Solo DL doesn't know about our
 * old style Honda ECU.  In theory this kind of bridge could
 * be used to integrate any kind of older equipement with
 * the newer SoloDL.
 *
 * Our "ports" are actually just 2 of 4 cables on a USB
 * quad RS-232 serial port conversion cable.  So we actually
 * have capacity to write out to another 2 ports if we
 * want to (future).
 *
 * As we pass data from the DL32 to the SoloDL, we do some
 * other stuff along the way:
 *
 * - transform DL32 input as needed with an input transform.
 *   It may not be "1:1" data, a given value might need to be
 *   pushed through a function to get the actual value you
 *   want.
 *
 * - copy the DL32 input to a data tap (broadcast) so other
 *   programs can listen in (on raw data).
 *
 * - copy out the normalized data to a data tap so other
 *   programs can listen in (on normal data) if they want to.
 *
 * - transform the normal data to SoloDL data, in some cases
 *   the output isn't 1:1, it needs to be pushed through a
 *   function to match what the SoloDL is expecting.
 *
 * - copy out the output data to a data tap so other
 *   programs can listen in (to SoloDL data) if they want to.
 *
 * - listen for any commands, we allow for commands to fetch
 *   status of our daemon, do a reload of configuation and
 *   do test mode operations, like manually set the value
 *   of a DL32 channel or configure what a given input or
 *   output transform should be.
 *
 * All of this happens within a 100ms cycle; the SoloDL expects
 * to be updated 10 times a second; some channels use a full 10hz
 * but others use 2hz or 5hz.  Other sample rates are possible,
 * but for now we only support those because their protocol calls
 * for it, and its easier to implement just those sample rates.
 *
 * The DL32 outputs data every 86ms or so.  We don't buffer DL32
 * data; if we skip a sample here and there, its fine, more samples
 * are on the way!
 *
 */

class ECUBridge : public Object {

  private:

    struct stats_t {

      long rx;
      long tx;
      long cmds;
      long uptime;

    } stats;

    /**
     *
     * cable - the USB Cable (the FTDI quad cable), will tell us
     * when its connected or disconnected.
     *
     */

    USBCable *cable;

    /**
     *
     * cmdPort - users can chat with the daemon via a stardard TCP
     * port.
     *
     */

    CommandPort *cmdPort;

    /**
     *
     * the data taps allow the ecu bridge to broad (to whoever
     * cares) the data passing through the bridge.
     *
     */

    DataTapWriter *rawTap;
    DataTapWriter *normalTap;
    DataTapWriter *outputTap;

    /**
     *
     * solodl - the dashboard monitor/camera
     *
     */

    SoloDLPort *solodl;

    /**
     *
     * dl32 - the dl32 that connects to the ECU in
     * the car
     *
     */

    DL32Port *dl32;

    /**
     *
     * portMapper - port mapper tells us where our devices
     * are.
     *
     */

    PortMapper *portMapper;

    /**
     *
     * channelMgr - our mapping from input through
     * transforms/filters to outputs.
     *
     */

    ChannelManager *channelMgr;

    /**
     *
     * running - true if we are already in
     * the main loop().
     *
     */

    bool running;

    /**
     *
     * breakbreak - flag to tell me to stop looping.
     *
     */

    bool breakbreak;

    /**
     *
     * timevalSubtract() - get an accurate "delta" for two
     * timeval structs (the kind we typically use with select()
     * calls etc.).  This allows us to get deltas without having
     * to worry about microsecond rollove retc.  That mess is
     * all handled here.
     *
     * @param result timeval - the delta being passed back
     *
     * @param x timeval - argument 1
     *
     * @param y timeval - argument 2
     *
     * @return bool - exactliy true is returned if x is less
     * than y.
     *
     */

    bool timevalSubtract(struct timeval *result,
                         struct timeval *x,
                         struct timeval *y);

    /**
     *
     * setFD() - helper to add a descriptor to
     * a select() file descriptor set
     *
     */

    bool setFD(int fd, fd_set *fds, int *max);

    /**
     *
     * clrFD() - helper to remove a descriptor from
     * a select() file descriptor set
     *
     */

    bool clrFD(int fd, fd_set *fds, int *max);

    /**
     *
     * monitorData() - for any of our data taps, we send out a line of data,
     * CSV style that has the format:
     *
     *    1,3,2,0,3,9,...
     *
     * Where the each *pair* is the channel number followed by the channel
     * value.  Any program monitoring can then step through the line in
     * pairs of columns.
     *
     * @param outputTap data tap object - the tap to write to.
     *
     * @data unsgined int array - the data to write, it must have values
     * in the indexes [1]..[15] (i.e. from 1 to the max number of Solo DL
     * channels).
     *
     * @return bool - exactly false on error.
     *
     */

    bool monitorData(DataTapWriter *tap, unsigned int *d);

    /**
     *
     * doCommand() - given a command from some client program, execute the
     * command and pass back results of the command as a string, which
     * should then be sent back (by the caller) the client.
     *
     * @param command string - the command to execute. General format for
     * all commands is CSV, with the first column being the command name,
     * and the other columns being arguments. For example:
     *
     *   echo,1,2,3
     *
     * Should result in output of "123" because the "echo" command just
     * echos the arguments.
     *
     * @param result string - the oputput of the command.
     *
     * @return bool - exactly false on error
     *
     */

    bool doCommand(const string & command, string & result);

  protected:

  public:

    /* standard constructor */

    ECUBridge(void);

    /**
     *
     * configure() - reset everything and start fresh.  This
     * method should be callable at any time so that if we need
     * to re-read the configuration file for exmaple, then
     * we can do that live, without have to shutdown and startup
     * again.
     *
     * @return bool - exactly false if something goes wrong.
     *
     */

    bool configure(void);

    /**
     *
     * clear() - forced reset, get ready to re-configure.
     *
     */

    bool clear(void);

    bool stop(void) {
      breakbreak = true;
    }

    /**
     *
     * loop() - this is the main processing loop, we pass
     * data from the DL-32 over to the SoloDL and do any
     * of the inbetween stuff as we go.
     *
     */

    bool loop(void);

    /* standard destructor */

    virtual ~ECUBridge();
};

#endif
