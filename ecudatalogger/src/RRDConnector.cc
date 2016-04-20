#include "RRDConnector.hh"

bool rrdExtraDebug = false;

/**
 *
 * configure() - reset and do whatever we have to in
 * order to connect to the RRD Daemon, and be ready
 * to process commands.  (This may block you until
 * the connection is ready).
 *
 */

bool RRDConnector::configure(void) {

  info("configure() - trying to connect to RRD...");

  /* reset */

  clear();

  /*
   * try to connect, if anything goes wrong, we sleep for a bit and
   * try again.  We do this because the RRD daemon may simply be not
   * running yet.
   *
   */

  int maxTry = 5;

  while(maxTry >= 0) {

    /* open a unix domain socket */

    if((fd = socket(AF_UNIX, SOCK_STREAM, 0)) == -1) {

      error(string("configure() - can not make socket: ") + strerror(errno));

    } else {

      /* try to connect */

      struct sockaddr_un remote;

      remote.sun_family = AF_UNIX;
      strcpy(remote.sun_path, RRD_UNIX_SOCET);

      int len = strlen(remote.sun_path) + sizeof(remote.sun_family);

      if(connect(fd, (struct sockaddr *)&remote, len) == -1) {

        error(string("configure() - can not make socket: ") + strerror(errno));

      } else {

        /* try to ping */

        if(!ping()) {

          error(string("configure() - can not ping RRD: ") + getError());

        } else {

          /* make sure we have the data log files */

          if(!createDataFiles()) {

            error(string("configure() - can not setup data log files: ") + getError());

          } else {

            /* at this point we are ready to chat! */

            makeReady();

            /* all done */

            return true;
          }
        }
      }
    }

    /* if we fall through, sleep and try again */

    info("configure() - can't connect to RRD, sleeping 5sec, and trying again...");

    sleep(5);
    maxTry--;

    info("configure() - woke up!");
  }

  error("configure() - could not connect to RRD even after several attempts.");
  return false;
}

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

bool RRDConnector::logData(RRDDataFile kind, vector<unsigned int> & d) {

  if(!isReady()) {
    error("logData() - can't log any data, object not ready.");
    return false;
  }

  if(dataFiles.count(kind) == 0) {
    error(string("logData() - unrecognized data file kind") + to_string((int)kind));
    return false;
  }

  if(d.size() == 0) {
    error("logData() - no data given.");
    return false;
  }

  if(d.size() < 16) {
    error(string("logData() - expected at least 15 data values (") + to_string(d.size()) + string(")"));
    return false;
  }

  string path = dataFiles[kind];

  if(path.size() == 0) {
    error("logData() - no data log file path.");
    return false;
  }

  string cmd = "update ";

  cmd += path;
  cmd += " ";
  cmd += to_string(time(NULL));

  static char buf[2048];

  sprintf(buf,
          ":%d:%d:%d:%d:%d:%d:%d:%d:%d:%d:%d:%d:%d:%d:%d",
          d[1],  d[2],  d[3],  d[4],  d[5],
          d[6],  d[7],  d[8],  d[9],  d[10],
          d[11], d[12], d[13], d[14], d[15]);

  cmd += buf;

  string status = "";
  vector<string> output;

  if(!doCommand(cmd, output, status)) {
    error(string("logData() - can't send data (") + status + string("): ") + getError());
    return false;
  }

  /* data has been logged */

  return true;
}

/**
 *
 * createDataFiles() - make sure the data files for the ECU data
 * logging are ready for use.  If they already exist, don't do
 * anything, otherwise create 'em.
 *
 * @return bool - exactly false on error.
 *
 */

bool RRDConnector::createDataFiles(void) {

  info("createDataFiles() - starts...");

  dataFiles[RRDDataFile::RAW]    = string(RRD_BASE_FOLDER) + "/ecu-data-raw.rrd";
  dataFiles[RRDDataFile::NORMAL] = string(RRD_BASE_FOLDER) + "/ecu-data-normal.rrd";
  dataFiles[RRDDataFile::OUTPUT] = string(RRD_BASE_FOLDER) + "/ecu-data-output.rrd";

  for(auto pair : dataFiles) {

    auto idx  = pair.first;
    auto path = pair.second;

    if(!file_exists(path)) {

      info(string("createDataFiles() - creating fresh RRD: ") + path);

      string limit = "86400";
      string max   = "2147483647";
      string cmd   = "CREATE ";

      cmd += path + " -s 1";

      cmd += string(" DS:rpm:GAUGE:600:0:") + max;
      cmd += string(" DS:wheelspeed:GAUGE:600:0:") + max;
      cmd += string(" DS:oilpressure:GAUGE:600:0:") + max;
      cmd += string(" DS:oiltemp:GAUGE:600:0:") + max;
      cmd += string(" DS:watertemp:GAUGE:600:0:") + max;
      cmd += string(" DS:fuelpressure:GAUGE:600:0:") + max;
      cmd += string(" DS:batteryvoltage:GAUGE:600:0:") + max;
      cmd += string(" DS:throttleangle:GAUGE:600:0:") + max;
      cmd += string(" DS:manifoldpressure:GAUGE:600:0:") + max;
      cmd += string(" DS:airchargetemp:GAUGE:600:0:") + max;
      cmd += string(" DS:exausttemp:GAUGE:600:0:") + max;
      cmd += string(" DS:lambda:GAUGE:600:0:") + max;
      cmd += string(" DS:fueltemp:GAUGE:600:0:") + max;
      cmd += string(" DS:gear:GAUGE:600:0:") + max;
      cmd += string(" DS:errorflag:GAUGE:600:0:") + max;

      cmd += string(" RRA:AVERAGE:0.5:1:") + limit;
      cmd += string(" RRA:MIN:0.5:12:") + limit;
      cmd += string(" RRA:MAX:0.5:12:") + limit;
      cmd += string(" RRA:AVERAGE:0.5:12:") + limit;
      cmd += string(" RRA:LAST:0.5:12:") + limit;

      string status = "";
      vector<string> output;

      if(!doCommand(cmd, output, status)) {
        error(string("createDataFiles() - problem creating file (") + status + string("): ") + getError());
        return false;
      }

    } else {

      info(string("createDataFiles() - file exists already: ") + path);
    }
  }

  info("createDataFiles() - done.");

  /* all done */

  return true;
}

/**
 *
 * ping() - check that we have a good connection to RRD.
 *
 * @return bool - exactly false on errror.
 *
 */

bool RRDConnector::ping(void) {

  /* just send a ping command to verify things are working */

  string command = "ping\n";
  vector<string> output;
  string status  = "";

  if(!doCommand(command, output, status)) {
    error(string("ping() - failed: ") + getError());
    return false;
  }

  if(output.size() != 0) {
    error(string("ping() - wrong # of output lines"));
    return false;
  }

  if(status != "PONG") {
    error(string("ping() - bad status: ") + status);
    return false;
  }

  /* all done */

  return true;
}

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

bool RRDConnector::doCommand(const string & command, vector<string> & output, string & status) {

  trace("doCommand() - starts... ");

  if(command.empty()) {
    error("doCommand() - no command provided.");
    return false;
  }

  output.clear();

  /*
   * protocol is line based...so if they didn't include a \n on the end
   * we add it.
   *
   */

  string todo = trim(command) + "\n";

  /* send over the command... */

  trace(string(string("doCommand() - sending (") + to_string(fd) + "): ") + command);

  if(send(fd, todo.c_str(), todo.size(), 0) == -1) {
    error(string("doCommand() - can't send command (") + command + string("): ") + strerror(errno));
    return false;
  }


  /*
   * results are first a line with # of response lines and status.
   * If # is < 0, its an error.
   *
   */

  string line;

  if(!readLine(line)) {
    error(string("doCommand() - problem reading status line: ") + getError());
    return false;
  }

  trace("doCommand() - parsing output...");

  /*
   * we've got a line, we now need to parse it into # of lines
   * and its status.
   *
   */

  regex firstLine("^(\\d+)\\s+(.+)$", regex_constants::ECMAScript|regex_constants::icase);
  smatch sm;

  if(!regex_match(line, sm, firstLine)) {
    error(string("doCommand() - bad RRD status line: ") + line);
    return false;
  }

  string token1 = sm[1];
  string token2 = sm[2];

  trace(string("doCommand() - status: ") + token2);

  status = token2;

  int nLines = strtol(token1.c_str(), NULL, 10);

  if(nLines < 0) {
    error(string("doCommand() - error from RRD: ") + token2);
    return false;
  }

  trace(string("doCommand() - reading ") + to_string(nLines) + " more lines of output...");

  /* read off the output lines */

  for(int i=0; i<nLines; i++) {

    if(!readLine(line)) {
      error(string("doCommand() problem reading output lines: ") + getError());
      return false;
    }

    output.push_back(line);

    trace(string("doCommand() - output: ") + line);
  }

  /* all output is passed back, we are done */

  return true;
}

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

bool RRDConnector::readLine(string & line) {

  trace(string("readLine() - reading next line (") + to_string(fd) + ")...");

  /*
   * leave room for 32K messages, we should never ever
   * need anything that big.
   *
   */

  static char buffer[1024*32];

  if(fd <= 0) {
    error("readLine() - not ready yet.");
    return false;
  }

  line = "";

  /* grab the next message (blocking) */

  int n = recv(fd, buffer, 1024*32, 0);

  if(n < 0) {
    error(string("readLine() - problem reading: ") + strerror(errno));
    return false;
  }
  buffer[n] = '\0';

  line = trim(buffer);

  trace(string("readLine() - line received: ") + line);

  /* all done */

  return true;

}

/* disconnect from the RRD daemon */

void RRDConnector::clear(void) {

  /* close the daemon connection */

  close(fd);

  fd = -1;

  unReady();
}
