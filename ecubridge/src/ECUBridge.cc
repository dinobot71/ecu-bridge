#include "ECUBridge.hh"
#include <math.h>

ECUBridge::ECUBridge(void) :
  Object("ECUBridge"), running(false), channelMgr(NULL), portMapper(NULL),
  dl32(NULL), solodl(NULL), rawTap(NULL), normalTap(NULL), outputTap(NULL),
  cmdPort(NULL), breakbreak(false), cable(NULL) {

  info("bridge is starting up...");

  unReady();

  if(!configure()) {

    /* something went wrong */

  } else {

    info("bridge is ready!");
  }

}

/**
 *
 * clear() - forced reset, get ready to re-configure.
 *
 */

bool ECUBridge::clear(void) {

  info("resetting...");

  running = false;

  if(channelMgr != NULL) {
    delete channelMgr;
    channelMgr = NULL;
  }

  if(portMapper != NULL) {
    delete portMapper;
    portMapper = NULL;
  }

  if(dl32 != NULL) {
    delete dl32;
    dl32 = NULL;
  }

  if(solodl != NULL) {
    delete solodl;
    solodl = NULL;
  }

  if(rawTap != NULL) {
    delete rawTap;
    rawTap = NULL;
  }

  if(normalTap != NULL) {
    delete normalTap;
    normalTap = NULL;
  }

  if(outputTap != NULL) {
    delete outputTap;
    outputTap = NULL;
  }

  if(cmdPort != NULL) {
    delete cmdPort;
    cmdPort = NULL;;
  }

  if(cable != NULL) {
    delete cable;
    cable = NULL;
  }

  return true;
}

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

bool ECUBridge::configure(void ) {

  clear();

  info("conifguring...");

  /*
   * setup the port mapper (tells us where to find the devices) When
   * we setup the port mapper and the DL-32/SoloDL, we have to be
   * prepared for them to not be ready yet...but become ready later,
   * since the user may have powered on the raspberry, and *then*
   * pluggged in the USB cables...after the daemon has already started.
   *
   */

  portMapper = new PortMapper();

  if(!portMapper->isReady()) {
    warning(string("configure() - can not find USB/Serial Ports: ") + portMapper->getError());
  }
  info("portmapper.");

  /* setup the DL-32 Port */

  if(portMapper->isReady()){

    string device = portMapper->getDevice(Device::DL32);

    if(device.empty()) {
      error("configure() - can not find DL-32 device.");
      return false;
    }

    /* open it */

    dl32 = new DL32Port(device);

    if(!dl32->isReady()) {
      warning(string("configure() - can not open DL-32: ") + dl32->getError());
      delete dl32;
      dl32 = NULL;
    }
  }

  info("dl32.");

  /* setup the SoloDL port */

  if(portMapper->isReady()){

    string device = portMapper->getDevice(Device::SOLODL);

    if(device.empty()) {
      error("configure() - can not find Solo DL device.");
      return false;
    }

    /* open it */

    solodl = new SoloDLPort(device);

    if(!solodl->isReady()) {
      warning(string("configure() - can not open Solo DL: ") + dl32->getError());
      delete solodl;
      solodl = NULL;
    }
  }
  info("solodl.");

  /* setup the data taps */

  {
    IniFile ini = ConfigManager::instance();

    if(!ini.isReady()) {
      error(string("configure() - can not get configuration file: ") + ini.getError());
      return false;
    }

    uint16_t  raw    = 0;
    uint16_t  normal = 0;
    uint16_t  output = 0;
    string    group  = "";
    string    tmp    = "";

    tmp = trim(ini.getValue("ECU Bridge", "data_tap_raw"));

    if(is_numeric(tmp)) {
      raw = (uint16_t)stol(tmp);
    }

    if(raw <= 0) {
      error("configure() - no 'raw' data tap port.");
      return false;
    }

    tmp = trim(ini.getValue("ECU Bridge", "data_tap_normal"));

    if(is_numeric(tmp)) {
      normal = (uint16_t)stol(tmp);
    }

    if(normal <= 0) {
      error("configure() - no 'raw' data tap port.");
      return false;
    }

    tmp = trim(ini.getValue("ECU Bridge", "data_tap_output"));

    if(is_numeric(tmp)) {
      output = (uint16_t)stol(tmp);
    }

    if(output <= 0) {
      error("configure() - no 'raw' data tap port.");
      return false;
    }

    tmp = trim(ini.getValue("ECU Bridge", "group_addr"));

    if(tmp.empty()) {
      error("configure() - no 'group_addr' address.");
      return false;
    }

    group = tmp;

    if((raw == normal)||(raw==output)||(normal==output)) {
      error("configure() - the data tap ports raw, normal and output must all be different.");
      return false;
    }

    /* ok, we have a good configuration, open the data taps */

    rawTap = new DataTapWriter(raw, group);
    if(!rawTap->isReady()) {
      error(string("configure() - can not open raw tap: ") + rawTap->getError());
      return false;
    }

    normalTap = new DataTapWriter(normal, group);
    if(!normalTap->isReady()) {
      error(string("configure() - can not open normal tap: ") + normalTap->getError());
      return false;
    }

    outputTap = new DataTapWriter(output, group);
    if(!outputTap->isReady()) {
      error(string("configure() - can not open output tap: ") + outputTap->getError());
      return false;
    }
  }
  info("data taps.");

  /* setup the command input */

  cmdPort = new CommandPort();

  if(!cmdPort->isReady()) {
    error("configure() - can not create command port.");
    return false;
  }
  info("command port");

  /* setup the USB cable */

  cable = new USBCable();
  if(!cable->isReady()) {
    error("configure() - can not setup USB cable.");
    return false;
  }
  info("usb cable");

  /* setup channel manager */

  channelMgr = new ChannelManager();

  if(!channelMgr->isReady()) {
    error("configure() - can not create channel manager.");
    return false;
  }
  info("channel manager.");

  /* at this point we should be ready to loop */

  makeReady();

  info("ready.");

  /* all done */

  return true;
}

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

bool ECUBridge::timevalSubtract(struct timeval *result,
                                struct timeval *x,
                                struct timeval *y) {

  /* Perform the carry for the later subtraction by updating y. */

  if (x->tv_usec < y->tv_usec) {
    int nsec    = (y->tv_usec - x->tv_usec) / 1000000 + 1;
    y->tv_usec -= 1000000 * nsec;
    y->tv_sec  += nsec;
  }
  if (x->tv_usec - y->tv_usec > 1000000) {
    int nsec    = (x->tv_usec - y->tv_usec) / 1000000;
    y->tv_usec += 1000000 * nsec;
    y->tv_sec  -= nsec;
  }

  /*
   * Compute the time remaining to wait.
   * tv_usec is certainly positive.
   *
   */

  result->tv_sec  = x->tv_sec - y->tv_sec;
  result->tv_usec = x->tv_usec - y->tv_usec;

  /* Return 1 if result is negative. */

  return x->tv_sec < y->tv_sec;
}

/**
 *
 * setFD() - helper to add a descriptor to
 * a select() file descriptor set
 *
 */

bool ECUBridge::setFD(int fd, fd_set *fds, int *max) {

  FD_SET(fd, fds);

  if (fd >= *max) {
    *max = fd + 1;
  }

  /* double check max */

  for(int d = 0; d < *max; d++) {

    if (FD_ISSET(d, fds)) {
      if(d >= *max) {
        *max = d + 1;
      }
    }
  }

  return true;
}

/**
 *
 * clrFD() - helper to remove a descriptor from
 * a select() file descriptor set
 *
 */

bool ECUBridge::clrFD(int fd, fd_set *fds, int *max) {

  FD_CLR(fd, fds);

  /* double check max */

  for(int d = 0; d < *max; d++) {

    if (FD_ISSET(d, fds)) {
      if(d >= *max) {
        *max = d + 1;
      }
    }
  }

  return true;
}

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

bool ECUBridge::monitorData(DataTapWriter *tap, unsigned int *d) {

  if(tap == NULL) {
    error("monitorData() - no tap.");
    return false;
  }

  if(d == NULL) {
    error("monitorData() - no data.");
    return false;
  }

  static char buffer[2048];

  sprintf(buffer,
          "%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d\n",
          1, d[1],   2,d[2],   3,d[3],  4,d[4],   5,d[5],
          6, d[6],   7,d[7],   8,d[8],  9,d[9],  10,d[10],
          11,d[11], 12,d[12], 13,d[13],14,d[14], 15,d[15]);

  if(!tap->send(buffer)) {
    error(string("monitorData() - failed to broadcast tap data: ") + tap->getError());
    return false;
  }

  /* all done */

  return true;
}

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
 * Commands:
 *
 *   echo <args> - just echo back
 *
 *   status - echo a quick summary of key statistics and overall status
 *
 *   filter <input|output> <chan> <kind> <args> - set an input or output
 *   filter and provide any arguments, manual filter needs 1 argument
 *   for example.
 *
 * @param result string - the oputput of the command.
 *
 * @return bool - exactly false on error
 *
 */

bool ECUBridge::doCommand(const string & command, string & result) {

  if(command.empty()) {
    error("doCommand() - no command.");
    return false;
  }

  result = "";

  vector<string> tokens;
  explode(command, ",", tokens);

  if(tokens.size() < 1) {
    error("doCommand() - no command.");
    return false;
  }

  string cmd = trim(strtolower(tokens[0]));

  info(string("doCommand() - executing: ") + command + "...");

  if(cmd == "echo") {

    /* just send back the appended arguments */

    for(int zz=1; zz<tokens.size(); zz++) {
      result += tokens[zz];
    }

  } else if(cmd == "stop") {

    breakbreak = true;

    result = "OK.  Stopping.";

  } else if(cmd == "channels") {

    /* we need at least a sub-command */

    if(tokens.size() >= 2) {

      string subCmd = trim(strtolower(tokens[1]));

      if(subCmd == "map") {

        /* if they include a 3rd argument it means make it terse */

        bool terse = false;
        if(tokens.size() >= 3) {
          terse = true;
        }

        if(!channelMgr->channelMap(result, terse)) {
          result = string("ERROR: problem fetching channel map: ") + channelMgr->getError();
          error(string("doCommand() - ") + result);
        }

      } else if(subCmd == "transform") {

        if(tokens.size() < 4) {

          result = "ERROR: transform sub-command is missing arguments.";
          error(string("doCommand() - syntax error: ") + result);

        } else {

          /* pull off the channel # to process */

          int channel;

          if(!is_numeric(tokens[2])) {
            result = string("ERROR: transform sub-command - bad channel # value: ") + tokens[2];
            error(string("doCommand() - syntax error: ") + result);
          } else {
            channel = strtol(tokens[2].c_str(), NULL, 10);
          }

          if((channel < 1) || (channel > 15)) {
            result = string("ERROR: transform sub-command - channel # value out of range: ") + tokens[2];
            error(string("doCommand() - syntax error: ") + result);
          }

          /* pull off the input value to apply to that channel */

          long value = 0;

          if(!is_numeric(tokens[3])) {
            result = string("ERROR: transform sub-command - requires number argument: ") + tokens[3];
            error(string("doCommand() - syntax error: ") + result);
          } else {
            value = strtol(tokens[3].c_str(),NULL, 10);
          }

          /* process it! */

          if(result.empty()) {

            unsigned int output = 0;

            if(!channelMgr->transform(channel, (unsigned int)value, output)) {

              result = string("ERROR: transform sub-command - problem transforming: ") + channelMgr->getError();
              error(string("doCommand() - syntax error: ") + result);

            } else {

              result = to_string(output);
            }
          }
        }

      } else {

        result = string("ERROR: unknown sub-command: ") + tokens[1];
        error(string("doCommand() - (channels) syntax error: ") + result);
      }

    } else {

      result = "no sub-command.";
      error(string("doCommand() - (channels) syntax error: ") + result);
    }

  } else if(cmd == "status") {

    string status = "";

    /* current state */

    if(!cable->isConnected()) {
      status += "status: USB Unplugged\n";
    } else {
      status += "status: OK\n";
    }

    if(!running) {
      status += "status: not running\n";
    }

    if(breakbreak) {
      status += "status: stopping...\n";
    }

    /* reads/writes */

    status += string(" reads: ") + to_string(stats.rx) + "\n";
    status += string("writes: ") + to_string(stats.tx) + "\n";

    /* uptime */

    status += string("uptime: ") + to_string(stats.uptime) + "\n";

    /* # user commands */

    status += string("  cmds: ") + to_string(stats.cmds) + "\n";

    /* config file location */

    status += string("config: ") + ConfigManager::instance().configFileName() + "\n";

    /* log file location */

    status += string("   log: ") + LogManager::getFileName() + "\n";

    result = status;

  } else if(cmd == "patch") {


    if(tokens.size() < 2) {

      result = "ERROR: patch is missing arguments.";
      error(string("doCommand() - syntax error: ") + result);

    } else {

      string subCmd = trim(strtolower(tokens[1]));

      if(subCmd == "reset") {

        if(!channelMgr->patchReset()) {
          result = string("ERROR: problem resetting patch table: ") + channelMgr->getError();
          error(string("doCommand() - syntax error: ") + result);
        } else {
          result = "OK. patch table reset.";
        }

      } else if(subCmd == "default") {

        if(!channelMgr->patchDefault()) {
          result = string("ERROR: problem resetting patch table: ") + channelMgr->getError();
          error(string("doCommand() - syntax error: ") + result);
        } else {
          result = "OK. patch table reset.";
        }

      } else if(subCmd == "swap") {

        if(tokens.size() < 4) {

          result = "ERROR: swap sub-command is missing arguments.";
          error(string("doCommand() - syntax error: ") + result);

        } else {

          /* pull of the channel #'s to swap */

          int chan1, chan2;

          if(!is_numeric(tokens[2])) {
            result = string("ERROR: bad channel #1 value: ") + tokens[2];
            error(string("doCommand() - syntax error: ") + result);
          } else {
            chan1 = strtol(tokens[2].c_str(), NULL, 10);
          }

          if((chan1 < 1) || (chan1 > 15)) {
            result = string("ERROR: channel #1 value out of range: ") + tokens[2];
            error(string("doCommand() - syntax error: ") + result);
          }

          if(!is_numeric(tokens[3])) {
            result = string("ERROR: bad channel #2 value: ") + tokens[3];
            error(string("doCommand() - syntax error: ") + result);
          } else {
            chan2 = strtol(tokens[3].c_str(), NULL, 10);
          }

          if((chan2 < 1) || (chan2 > 15)) {
            result = string("ERROR: channel #2 value out of range: ") + tokens[3];
            error(string("doCommand() - syntax error: ") + result);
          }

          /* swap 'em */

          if(result.empty()) {

            if(!channelMgr->patch(chan1, chan2)) {

              result = string("ERROR: problem swapping channels: ") + channelMgr->getError();
              error(string("doCommand() - syntax error: ") + result);

            } else {

              result = "OK. swapped.";
            }
          }

        }

      } else {

        result = string("ERROR: patch unrecognized sub-sommcnad: ") + subCmd;
        error(string("doCommand() - syntax error: ") + result);
      }
    }

  } else if(cmd == "filter") {

    /*
     * expecting stuff like:
     *
     *    filter input 1 manual 3
     *
     */

    if(tokens.size() < 4) {

      result = "ERROR: filter is missing arguments.";
      error(string("doCommand() - syntax error: ") + result);

    }

    /* get the side (either input or output */

    string kind = trim(strtolower(tokens[1]));

    if((kind != "input")&&(kind != "output")) {
      result = string("ERROR: bad filter side (must be input or output): ") + kind;
      error(string("doCommand() - syntax error: ") + result);
    }

    /* get the channel */

    int channel = 0;

    if(!is_numeric(tokens[2])) {
      result = string("ERROR: bad channel number: ") + tokens[2];
      error(string("doCommand() - syntax error: ") + result);
    } else {
      channel = strtol(tokens[2].c_str(), NULL, 10);
    }

    if((channel < 1) || (channel > 15)) {
      result = string("ERROR: channel out of range: ") + tokens[2];
      error(string("doCommand() - syntax error: ") + result);
    }

    /* get the kind of filter */

    string filter = trim(strtolower(tokens[3]));

    if((filter != "null")&&(filter != "passthrough")&&(filter != "manual")) {
      result = string("ERROR: bad filter kind (must be null, passthrough, or manual): ") + filter;
      error(string("doCommand() - syntax error: ") + result);
    }

    long value = 0;

    if(filter == "manual") {

      if(tokens.size() < 5) {
        result = string("ERROR: manual filter requires value argument: ");
        error(string("doCommand() - syntax error: ") + result);
      } else {
        if(!is_numeric(tokens[4])) {
          result = string("ERROR: manual filter requires number argument: ") + tokens[4];
          error(string("doCommand() - syntax error: ") + result);
        } else {
          value = strtol(tokens[4].c_str(),NULL, 10);
        }
      }
    }

    /* if no parameter errors, do it! */

    if(result.empty()) {

      /* make the new filter */

      DataTransformer *replace = NULL;

      if(filter == "null") {
        replace = new NullTransform();
      } else if(filter == "passthrough") {
        replace = new PassthroughTransform();
      } else if(filter == "manual") {
        replace = new ManualTransform();
        replace->setParam(0, (unsigned int)value);
        info(string("doCommand() - manual filter of ") + to_string(value) + string(") for ") + kind);
      }

      /* install on the correct side and for the correct channel */

      if(kind == "input") {

        if(!channelMgr->setInputFilter(channel, replace)) {
          result = string("ERROR: problem setting input filter.");
          error(string("doCommand() - can not set filter: ") + channelMgr->getError());
        }

      } else {

        if(!channelMgr->setOutputFilter(channel, replace)) {
          result = string("ERROR: problem setting output filter.");
          error(string("doCommand() - can not set filter: ") + channelMgr->getError());
        }
      }
    }

    if(result.empty()) {
      result = "OK. Filter set.";
    }

  } else {

    error(string("doCommand() - unknown command (") + cmd + string(")."));

    result = string("ERROR: Unknown command: ") + cmd;
  }

  info(string("doCommand() done."));

  /* all done */

  return true;
}

/**
 *
 * loop() - this is the main processing loop, we pass
 * data from the DL-32 over to the SoloDL and do any
 * of the in between stuff as we go.
 *
 */

bool ECUBridge::loop(void) {

  if(!isReady()) {
    error("loop() - can't run, bridge is not configured.");
    return false;
  }

  if(running) {
    error("loop() - we are already running!");
    return false;
  }

  info("loop() - ready to run..");

  /*
   * main loop! Basically our job is to wait for DL-32
   * data to arrive (every 86-89ms), and regardless, every
   * 100ms (10hz) we have to send whatever we haver to
   * the SolODL.  Given the mis-match in frequencies and
   * that we don't have any synchronization between the
   * devices...we just keep a "current value" and when
   * we get DL-32 data, we set the current value.  When
   * we need to send to the Solo DL, we send the current
   * value.  No fancy buffering.
   *
   * The sampling rate of 2, 5 or 10hz (depending on the
   * channel) means even we get a sample wrong...a better
   * one is on the way very soon. We're not intending to
   * have absolute perfection here, its a bridge to legacy
   * equipment, just do the best we can.
   *
   */

  /* setup for selecting (event watching */

  int maxfd = 0;

  fd_set readfds;
  fd_set writefds;
  fd_set exceptfds;

  /*
   * setup the "current value" essentially the vectors of raw, processed (normal) and
   * and output data.
   *
   */

  unsigned int rawData[SoloDLChannelMax+1];
  unsigned int normalData[SoloDLChannelMax+1];
  unsigned int outputData[SoloDLChannelMax+1];

  {
    for(int zz=0; zz<=SoloDLChannelMax; zz++) {

      rawData[zz]    = 0;
      normalData[zz] = 0;
      outputData[zz] = 0;
    }
  }

  /* reset stats to 0 */

  stats.rx     = 0;
  stats.tx     = 0;
  stats.cmds   = 0;
  stats.uptime = 0;

  /*
   * setup for timing, we need to track up time, and we need to have a regular
   * cycle of 100ms, regardless of having data from the DL-32 or not.
   *
   */

  struct timeval     selectTimeout;
  time_t started   = time(NULL);
  time_t checkTime = started;

  timeval tv1;
  timeval tv2;
  timeval tv3;
  timeval tv4;
  timeval perf1;
  timeval perf2;
  timeval perf3;
  timeval result;
  timeval lastDL32;
  timeval lastSoloDL;
  timeval startDL32;
  timeval startSoloDL;

  float   cycleTime = 0.0;

  if(gettimeofday(&tv1, NULL) != 0) {
    error("loop() - can't get time of day.");
    return false;
  }
  lastDL32   = tv1;
  lastSoloDL = tv1;

  selectTimeout.tv_sec  = 0;
  selectTimeout.tv_usec = 10 * 1000; /* 100ms (10hz) timeouts */

  /* ok, we are good to go */

  info("loop() - entering event loop...");

  running = true;

  long delta                = 0;
  long prevDelta            = 0;
  long workTime             = 0;
  long prevWorkTime         = 0;
  long sleepFor             = 0;
  long prevSleepFor         = 0;

  bool debugTiming = false;

  /*
   * loop until we tell ourselves to stop.  We do what we can to keep
   * things in sync for our 100ms window on sending to the SoloDL, but
   * because we are all in one thread, there are going to be cases
   * where we miss the mark. When we come out of a select() if we are
   * close but not at the timeout and the DL-32 is ready...we'll do
   * that work, andn possible be over the mark...if say the DL-32
   * reading time was more than the remaining time to the next send
   * mark.
   *
   * We can't do much about that without a lot more complexity; using
   * threads, hardware interrupts etc.  Given that those boundary conditions
   * happen infrequently, and the SoloDL doesn't seem bothered by it...
   * we use it as is.
   *
   * If we ever need to be super precise though, we should move to a
   * a threaded model or even use separate processes for reading / writing;
   * and then have a shared memory chunk between the two so that the
   * DL-32 can contribute its data (via semaphore), and let the kernel
   * handle slicing the processes (effectively doing threading for us).
   *
   * For now its a single thread / loop and is deemed accruate enough.
   *
   */

  while(!breakbreak) {

    /*
     * track the cycle time in ms, to make sure we are hitting
     * our 100ms mark.
     *
     */

    tv3 = tv1;

    if(gettimeofday(&tv1, NULL) != 0) {
      error("loop() - can't get time of day.");
      running = false;
      return false;
    }

    /* wait for something to happen */

    FD_ZERO(&readfds);
    FD_ZERO(&writefds);
    FD_ZERO(&exceptfds);
    maxfd = 0;

    if(dl32 != NULL) {

      int h = dl32->getHandle();

      FD_SET(h, &readfds);
      if(h >= maxfd) {
        maxfd = h;
      }
    }

    if(cmdPort != NULL) {

      int h = cmdPort->getHandle();

      FD_SET(h, &readfds);
      if(h >= maxfd) {
        maxfd = h;
      }
    }

    if(cable != NULL) {

      int h = cable->getHandle();

      FD_SET(h, &readfds);
      if(h >= maxfd) {
        maxfd = h;
      }
    }

    maxfd++;

    long waitTime   = selectTimeout.tv_usec;
    long actualWait = waitTime;
    long winRemain  = 0;

    gettimeofday(&perf1, NULL);

    if(selectTimeout.tv_usec < 1000) {

      /*
       * if we are waiting for < 1ms, try to get more precise by
       * using nanosleep() for the delay, and the select only
       * for the file descriptor test.
       *
       */

      struct timespec req;
      req.tv_sec  = 0;
      req.tv_nsec = selectTimeout.tv_usec * 1000;

      nanosleep(&req, NULL);

      selectTimeout.tv_sec  = 0;
      selectTimeout.tv_usec = 0;
    }

    int status = select(maxfd, &readfds, NULL, NULL, &selectTimeout);

    gettimeofday(&perf2, NULL);

    timersub(&perf2, &perf1, &result);
    actualWait = result.tv_usec;

    if(debugTiming) {
      info(string("X: req. wait: ") + to_string(waitTime) + string(" act. wait: ") + to_string(actualWait));
    }

    /* - - - - start of work block - - - - - - */

    /* what happened? */

    if(status < 0) {

      error(string("loop() - select error: ") + strerror(errno));
      error(string("loop() - maxfd: ") + to_string(maxfd) + string(" wait: ") + to_string(waitTime));
      running = false;
      return false;

    }

    /*
     * if we got a USB event, process that first...since it means we
     * may not be able to send data, or must stop.
     *
     */

    if(FD_ISSET(cable->getHandle(), &readfds)) {

      /*
       * the call to getEvent() will force a cable re-scan,
       * so afterwards if we ask for isConnected() we'll
       * get the appropriate status.
       *
       * NOTE: we may get multiple events!  The quad cable
       * generates 5 add or 5 remove events if you plugin it in
       * or take it out.  We have to process them all, but it
       * doesn't take long...and it should happen rarely anyways.
       *
       */

      USBEvent kind;
      bool oldStatus = cable->isConnected();
      bool newStatus = oldStatus;

      if(!cable->getEvent(kind)) {
        warning(string("loop() - can not determine USB cable status: ")  + cable->getError());
        continue;
      }

      switch(kind) {

        case USBEvent::ADD:
          info("loop() - USB add detected.");
          break;

        case USBEvent::REMOVE:
          info("loop() - USB remove detected.");
          break;

        case USBEvent::CHANGE:
          info("loop() - USB change detected.");
          break;

        case USBEvent::MOVE:
          info("loop() - USB move detected.");
          break;

        case USBEvent::ONLINE:
          info("loop() - USB online detected.");
          break;

        case USBEvent::OFFLINE:
          info("loop() - USB offline detected.");
          break;
      }

      newStatus = cable->isConnected();

      if(oldStatus != newStatus) {

        if(newStatus) {

          info("loop() - USB cable is connected!!");

          /* have to try and open the ports again */

          if(!portMapper->configure()) {
            error(string("loop() - can not reconfigure port mapper: ") + portMapper->getError());
            running = false;
            return false;
          }
          info("portmapper.");

          /* setup the DL-32 Port */

          {
            string device = portMapper->getDevice(Device::DL32);

            if(device.empty()) {
              error("loop() - can not find DL-32 device.");
              running = false;
              return false;
            }

            /* open it */

            dl32 = new DL32Port(device);

            if(!dl32->isReady()) {
              error(string("loop() - can not open DL-32: ") + dl32->getError());
              running = false;
              return false;
            }
          }
          info("dl32.");

          /* setup the SoloDL port */

          {
            string device = portMapper->getDevice(Device::SOLODL);

            if(device.empty()) {
              error("loop() - can not find Solo DL device.");
              running = false;
              return false;
            }

            /* open it */

            solodl = new SoloDLPort(device);

            if(!solodl->isReady()) {
              error(string("loop() - can not open Solo DL: ") + dl32->getError());
              running = false;
              return false;
            }
          }
          info("solodl.");
          info("loop() - DL-32/SoloDL ports have re-connected.");

        } else {

          info("loop() - USB cable has been unplugged!!");

          /* we to remove the ports they aren't usable now. */

          delete dl32;
          dl32 = NULL;

          delete solodl;
          solodl = NULL;

          info("loop() - DL-32/SoloDL ports have been closed.");
        }
      }
    }

    /*
     * we have to send out to the Solo DL every 100ms (10hz),
     * but only if the USB cable is actually connected.
     *
     */

    bool doSend = false;

    timersub(&perf2, &lastSoloDL, &result);
    winRemain = 100.0 - (result.tv_usec / 1000.0);

    if(debugTiming) {
      info(string("X: time to send: ") + to_string(winRemain));
    }
    if(winRemain <= 1.0 ) {

      /* less than a milisecond away, send it now. */

      doSend = true;

    } else if((winRemain <= 3.0) && (cable->isConnected()) && (FD_ISSET(dl32->getHandle(), &readfds))) {

      /*
       * we are likely going to suck up as much or more time reading from DL-32 than we
       * actually have until we should be sending...so just send now, its a little early
       * but better than missing our window.
       *
       */

      if(debugTiming) {

        info("X: sending early.");
        doSend = true;
      }
    }

    if(doSend) {

      gettimeofday(&startSoloDL, NULL);

      if(cable->isConnected()) {

        /*
         * we timed out, we are at the 100ms mark, we need to send
         * data to the Solo DL
         *
         */

        if(!channelMgr->load(rawData, normalData, outputData)) {

          warning(string("loop() - failed to load data: ") + channelMgr->getError());

        } else {

          /* send it */

          if(!solodl->writeSamples(outputData)) {

            warning(string("loop() - failed to send data: ") + solodl->getError());

          } else {

            /* data was sent, update stats */

            stats.tx++;

            if((stats.tx % 600) == 0) {

              /* warn once a minute if the DL-32 appears to be off line */

              if(stats.rx == 0) {
                warning("loop() - sending to SoloDL but not receiving anything from DL-32.  Is it powered?");
              }
            }

            /*
             * send the data out to the data taps to let other
             * programs monitor what we are doing.
             *
             */

            if(!monitorData(rawTap,    rawData)) {
              warning(string("loop() - failed to tap raw data: ") + getError());
            }

            if(!monitorData(normalTap, normalData)) {
              warning(string("loop() - failed to tap normal data: ") + getError());
            }

            if(!monitorData(outputTap, outputData)) {
              warning(string("loop() - failed to tap output data: ") + getError());
            }
          }
        }
      }

      gettimeofday(&lastSoloDL, NULL);

      if(debugTiming) {

        info(string("X: Solo DL cycle: ") + to_string(100.0 - winRemain));

        timersub(&lastSoloDL, &startSoloDL, &result);

        info(string("X: Solo DL work time: ") + to_string(result.tv_usec / 1000.0));
      }

    }

    /*
     * if the DL-32 is ready with data, we grab it.  But
     * only if the USB cable is connected.
     *
     */

    if(cable->isConnected()) {

      if(FD_ISSET(dl32->getHandle(), &readfds)) {

        gettimeofday(&startDL32, NULL);

        timersub(&startDL32, &lastDL32, &result);
        long sinceDL32 = result.tv_usec / 1000.0;

        /* we have sample data, set the "current value" */

        if(!dl32->readSamples(rawData)) {

          warning(string("loop() - failed to read samples correctly from DL-32:") + dl32->getError());

        } else {

          /* update stats */

          stats.rx++;
        }

        gettimeofday(&lastDL32, NULL);

        if(debugTiming) {

          info(string("X: DL-32 cycle: ") + to_string(sinceDL32));

          timersub(&lastDL32, &startDL32, &result);

          info(string("X: DL-32 work time: ") + to_string(result.tv_usec / 1000.0));
        }
      }
    }

    /*
     * even if they don't have the cable plugged in, we can still
     * do commands.
     *
     */

    if(FD_ISSET(cmdPort->getHandle(), &readfds)) {

      /* we have a client trying to do a command */

      if(!cmdPort->accept()) {

        warning(string("loop() - could not accept client command: ") + cmdPort->getError());

      } else {

        string command = "";

        /* read the command */

        if(!cmdPort->receive(command)) {

          warning(string("loop() - could not receive command: ") + cmdPort->getError());

        } else {

          /* do the command and send back the results */

          command = trim(command);

          string result = "END";

          if(!doCommand(command, result)) {

            warning(string("loop() - could not do command (") + command + string("): ") + getError());
            result = "ERROR: Could not execute command.";
          }

          if(!cmdPort->send(result)) {

            warning(string("loop() - could not send command (") + command + string(") results: ") + cmdPort->getError());
          }
        }

        /* each client gets one command at a time (we only have 100ms to work with) */

        cmdPort->drop();
      }

      /* update stats */

      stats.cmds++;
    }

    gettimeofday(&perf3, NULL);

    /* - - - - end of work block - - - - - - */

    /*
     * we did some work, now figure out how to set our timeout
     * so we hit the next 100ms mark.
     *
     */

    checkTime = time(NULL);

    stats.uptime = checkTime - started;

    if(gettimeofday(&tv2, NULL) != 0) {
      error("can't get time of day.");
      return false;
    }

    /*
     * regardless of when commands arrive or when DL-32 data
     * arrives, we have to be sending data to the SoloDL every
     * 100ms (10hz).  So we must be relative to lastSoloDL,
     * we want to be waking up again no more than 100ms after
     * lastSoloDL.  If we wake up for other reasons (before
     * then), DL-32 or commands...then re-calculate the remainder
     * of wait time for that 100ms cycle.
     *
     */

    prevDelta     = delta;

    timersub(&tv2, &lastSoloDL, &result);

    delta         = result.tv_usec;

    long cycle    = 100 * 1000;
    prevSleepFor  = sleepFor;
    sleepFor      = cycle - delta;

    if(true) {

      /*
       * try to make a fine grained adjustment; we may end up
       * arriving late (sleeping too long) or being swapped
       * out etc.   So don't actually wait the full time we
       * want to; if the sleep time is more than 1ms, then we
       * take off 1ms.  We hope to trade more calls to select()
       * for a chance to zero in on our target window size.
       *
       * For actual precise timing; standard Linux doesn't do
       * precise; it only does things with +/- 1ms of precision,
       * and anyways if you call a system call (like say select())
       * then you are yielding, and you may not get control back
       * when you think...it may be sooner or later depending on
       * what else is going on with other programs, interrupts
       * etc.
       *
       * To actually be precise with our send window, we would have
       * to move to using hardware timers.  We probably don't need
       * that though, as long as we are reasonably close to sending
       * stuff to the Solo DL at 10Hz. we should be ok.
       *
       *
       */

      if(sleepFor >= 1000) {
        sleepFor -= 1000;
      }
    }

    if(sleepFor < 0) {

      /*
       * we are running late, we don't expect to have anything better than about
       * 1ms resolution, and we expect to be late some times becuase we have both
       * read and write jammed into the same thread, but if things look really late,
       * take note.
       *
       */

      if(((delta/1000.0)-100.0) >= 10.0) {
        warning(string("loop() - send window too big (>100ms): ") + to_string(delta/1000.0) + "ms.");
      }

      sleepFor = 0;

    } else if(sleepFor > cycle) {

      warning(string("loop() - trying to sleep too long (>100ms): ") + to_string(sleepFor));

      sleepFor = cycle;
    }

    if(debugTiming) {
      info(string("X: delta: ") + to_string(delta) + string(" sleep: ") + to_string(sleepFor));
    }

    selectTimeout.tv_sec  = 0;
    selectTimeout.tv_usec = sleepFor;

  }

  running = false;

  return true;
}

/* standard destructor */

ECUBridge::~ECUBridge() {
  clear();
}
