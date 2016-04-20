#include "ChannelManager.hh"

/* standard constructor */

ChannelManager::ChannelManager(void) : Object("ChannelManager") {

  unReady();

  info("starting up...");

  {
    for(int i=0; i<(CMMaxChannels+1); i++) {

      inputTrans[i]   = NULL;
      inputFilter[i]  = NULL;
      outputFilter[i] = NULL;
      outputTrans[i]  = NULL;
    }
  }

  {
    for(int i=0; i<(CMMaxChannels+1); i++) {
      patchTable[i]        = 0;
      patchTableOrig[i]    = 0;
      patchTableDefault[i] = i;
    }
  }

  if(!configure()) {

    /* there was a problem! */

  } else {
    info("ready.");
  }
}

/**
 *
 * channelMap() - fetch a printout of the channel mapping that
 * a human can read.  If you do terse mode it will be provided in
 * CSV format.
 *
 * @param mapping string - the resulting dump of the channel map.
 *
 * @param terse bool - true if you want CSV.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::channelMap(string & mapping, bool terse) {

  mapping = "";

  for(int dst=1; dst<=CMMaxChannels; dst++) {

    string outputf = outputFilter[dst]->getName();
    string outputt = outputTrans[dst]->getName();

    int src        = patchTableInverted[dst];

    string inputt  = inputTrans[src]->getName();
    string inputf  = inputFilter[src]->getName();

    if(inputf == "Manual") {
      inputf = inputFilter[src]->getName() + string(" (") + to_string(inputFilter[src]->getParam(0)) + ")";
    }
    if(outputf == "Manual") {
      outputf = outputFilter[dst]->getName() + string(" (") + to_string(outputFilter[dst]->getParam(0)) + ")";
    }

    static char buf[1024];

    if(terse) {
      sprintf(buf, "%8s,%12s,%12s,%16s,%2d:%2d\n", inputt.c_str(), inputf.c_str(), outputf.c_str(), outputt.c_str(), src, dst);
    } else {
      sprintf(buf, "DL-32 > [%8s][%12s] %2d..%2d [%12s][%16s] > SoloDL\n", inputt.c_str(), inputf.c_str(), src, dst, outputf.c_str(), outputt.c_str());
    }
    mapping += buf;
  }

  /* all done */

  return true;
}

/**
 *
 * setOutputFilter() - install a new filter (output side).  You must
 * provide the channel its for and the new filter.
 *
 * @param chan int - the channel (1..15)
 *
 * @param filter data transformer - the filter to install.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::setOutputFilter(int chan, DataTransformer *filter) {

  if(!isReady()) {
    error("setOutputFilter() - object not ready.");
    return false;
  }

  if(filter == NULL) {
    error("setOutputFilter() - no filter provided.");
    return false;
  }

  if((chan < 1)||(chan > CMMaxChannels)) {
    error("setOutputFilter() - channel # is out of range.");
    return false;
  }

  /* get rid of old one */

  if(outputFilter[chan] != NULL) {
    delete outputFilter[chan];
    outputFilter[chan] = NULL;
  }

  /* install */

  outputFilter[chan] = filter;

  /* all done */

  return true;
}

/**
 *
 * setInputFilter() - install a new filter (input side).  You must
 * provide the channel its for and the new filter.
 *
 * @param chan int - the channel (1..15)
 *
 * @param filter data transformer - the filter to install.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::setInputFilter(int chan, DataTransformer *filter) {

  if(!isReady()) {
    error("setInputFilter() - object not ready.");
    return false;
  }

  if(filter == NULL) {
    error("setInputFilter() - no filter provided.");
    return false;
  }

  if((chan < 1)||(chan > CMMaxChannels)) {
    error("setInputFilter() - channel # is out of range.");
    return false;
  }

  /* get rid of old one */

  if(inputFilter[chan] != NULL) {
    delete inputFilter[chan];
    inputFilter[chan] = NULL;
  }

  /* install */

  inputFilter[chan] = filter;

  /* all done */

  return true;
}

/**
 *
 * invertPatchTable() - internal helper function, when we swap channels
 * around, we have to keep an inverted map, so we can look up the linkage
 * quickly, without have to scan the patch table for what goes where.
 *
 */

bool ChannelManager::invertPatchTable(void) {

  for(int src=0; src<=CMMaxChannels; src++) {

    int dst = patchTable[src];

    patchTableInverted[dst] = src;

  }
}

/**
 *
 * configure() - reset everything and start fresh.  This
 * method should be callable at any time so that if we need
 * to re-read the configuration file for example, then
 * we can do that live, without have to shutdown and startup
 * again.
 *
 * @return bool - exactly false if something goes wrong.
 *
 */

bool ChannelManager::configure(void) {

  /* if we are already setup, do a forced reset... */

  if(isReady()) {
    clear();
  }

  /* fetch configuration */

  IniFile ini = ConfigManager::instance();

  if(!ini.isReady()) {

    /* we can't read/find the configuration file? */

    error("configure() - can not load configuration manager.  Missing config.ini file?");
    return false;
  }

  /* load the input transforms */

  inputTrans[1]  = new DL32Chan1Transform();
  inputTrans[2]  = new DL32Chan2Transform();
  inputTrans[3]  = new DL32Chan3Transform();
  inputTrans[4]  = new DL32Chan4Transform();
  inputTrans[5]  = new DL32Chan5Transform();
  inputTrans[6]  = new NullTransform();
  inputTrans[7]  = new NullTransform();
  inputTrans[8]  = new NullTransform();
  inputTrans[9]  = new NullTransform();
  inputTrans[10] = new NullTransform();
  inputTrans[11] = new NullTransform();
  inputTrans[12] = new NullTransform();
  inputTrans[13] = new NullTransform();
  inputTrans[14] = new NullTransform();
  inputTrans[15] = new NullTransform();

  /* load the output transforms */

  outputTrans[1]  = new AIMRPMTransform();
  outputTrans[2]  = new AIMWheelSpeedTransform();
  outputTrans[3]  = new AIMOilPressTransform();
  outputTrans[4]  = new AIMOilTempTransform();
  outputTrans[5]  = new AIMWaterTempTransform();
  outputTrans[6]  = new AIMFuelPressTransform();
  outputTrans[7]  = new AIMBattVoltTransform();
  outputTrans[8]  = new AIMThrotAngTransform();
  outputTrans[9]  = new AIMManifPressTransform();
  outputTrans[10] = new AIMAirChargeTempTransform();
  outputTrans[11] = new AIMExhTempTransform();
  outputTrans[12] = new AIMLambdaTransform();
  outputTrans[13] = new AIMFuelTempTransform();
  outputTrans[14] = new AIMGearTransform();
  outputTrans[15] = new AIMErrorFlagTransform();

  /* figure out the input transform/filters (configurable) */

  {
    for(int i=1; i<=CMMaxChannels; i++) {

      /* work on next channel */

      vector<string> args;
      string chanName = string("chan_") + to_string(i);

      string value = ini.getValue("input filter", chanName);

      value = trim(strtolower(value));

      if(value.empty()) {
        error(string("configure() - missing input filter on input channel ") + chanName);
        clear();
        return false;
      }

      if(value == "null") {
        inputFilter[i] = new NullTransform();
        continue;
      }

      if(value == "passthrough") {
        inputFilter[i] = new PassthroughTransform();
        continue;
      }

      explode(value, " ,\t", args);

      string manual = trim(strtolower(args[0]));

      if(manual == "manual") {

        if(args.size()<2) {
          error(string("configure() - manual filter requires a value on input channel:") + chanName);
          clear();
          return false;
        }

        if(!is_numeric(args[1])) {
          error(string("configure() - non-numeric manual value (") + args[1] + string(") on input channel ") + chanName);
          clear();
          return false;
        }

        unsigned int paramA = (unsigned int)strtol(args[1].c_str(), NULL, 10);

        inputFilter[i] = new ManualTransform();
        inputFilter[i]->setParam(0, paramA);
        continue;
      }

      /* if we fall through, we don't recognize this filter */

      error(string("configure() - bad output filter (") + manual + string(") filter on input channel ") + chanName);
      clear();
      return false;
    }
  }

  /* figure out the output filter/transforms (configurable) */

  {
    for(int i=1; i<=CMMaxChannels; i++) {

      /* work on next channel */

      vector<string> args;
      string chanName = string("chan_") + to_string(i);

      string value = ini.getValue("output filter", chanName);

      value = trim(strtolower(value));

      if(value.empty()) {
        error(string("configure() - missing input filter on output channel ") + chanName);
        clear();
        return false;
      }

      if(value == "null") {
        outputFilter[i] = new NullTransform();
        continue;
      }

      if(value == "passthrough") {
        outputFilter[i] = new PassthroughTransform();
        continue;
      }

      explode(value, " ,\t", args);

      string manual = trim(strtolower(args[0]));

      if(manual == "manual") {

        if(args.size()<2) {
          error(string("configure() - manual filter requires a value on output channel:") + chanName);
          clear();
          return false;
        }

        if(!is_numeric(args[1])) {
          error(string("configure() - non-numeric manual value (") + args[1] + string(") on output channel ") + chanName);
          clear();
          return false;
        }

        unsigned int paramA = (unsigned int)strtol(args[1].c_str(), NULL, 10);

        outputFilter[i] = new ManualTransform();
        outputFilter[i]->setParam(0, paramA);
        continue;
      }

      /* if we fall through, we don't recognize this filter */

      error(string("configure() - bad filter (") + manual + string(") filter on output channel ") + chanName);
      clear();
      return false;
    }
  }

  /* figure out the patch ordering (configurable) */

  {
    for(int i=1; i<=CMMaxChannels; i++) {

      /* work on next channel */

      string chanName = string("patch_") + to_string(i);

      string value = ini.getValue("ECU Bridge", chanName);

      value = trim(strtolower(value));

      if(value.empty()) {
        error(string("configure() - missing patch order: ") + chanName);
        clear();
        return false;
      }

      if(!is_numeric(value)) {
        error(string("configure() - non-numeric patch value (") + value + string(") on channel ") + chanName);
        clear();
        return false;
      }

      int order = (int)strtol(value.c_str(), NULL, 10);

      /* make sure its in range */

      if((order<1)||(order>CMMaxChannels)) {
        error(string("configure() - channel patch order out of range (") + to_string(order) + string(") on channel ") + chanName);
        clear();
        return false;
      }

      /* make sure, this order iosn't already assigned */

      for(int j=1; j<i; j++) {
        if(patchTable[j] == order) {
          error(string("configure() - channel patch order already assigned (") + to_string(order) + string(") on channel ") + chanName);
          clear();
          return false;
        }
      }

      /* looks good, patch it. */

      patchTable[i] = order;
    }
  }

  /* double check that all patch orders are set */

  {
    int sum1 = 0;
    int sum2 = 0;

    for(int z=1; z<=CMMaxChannels; z++) {
      sum1 += z;
    }
    for(int zz=1; zz<=CMMaxChannels; zz++) {
      sum2 += patchTable[zz];
    }

    if(sum1 != sum2) {
      error("configure() - patch ordering appears corrupt.");
      clear();
      return false;
    }
  }

  /*
   * make a copy of the patch table, so we can reset to original
   * settings if we mess it up.
   *
   */

  {
    for(int zz=1; zz<=CMMaxChannels; zz++) {
      patchTableOrig[zz] = patchTable[zz];
    }
  }

  /* make sure the inverse of the patch table is up to date */

  invertPatchTable();

  /*
   * at this point we can transform input on the left from the DL-32 to
   * output on the right that goes to the SoloDL.
   *
   */

  makeReady();

  /* show the configuration */

  {
    info("configure() -- configured...");

    for(int chan=1; chan<=CMMaxChannels; chan++) {

      string inputt = inputTrans[chan]->getName();
      string inputf = inputFilter[chan]->getName();

      string outputf = outputFilter[patchTable[chan]]->getName();
      string outputt = outputTrans[patchTable[chan]]->getName();

      if(inputf == "Manual") {
        inputf = inputFilter[chan]->getName() + string(" (") + to_string(inputFilter[chan]->getParam(0)) + ")";
      }
      if(outputf == "Manual") {
        outputf = inputFilter[chan]->getName() + string(" (") + to_string(inputFilter[chan]->getParam(0)) + ")";
      }

      char buf[1024];
      sprintf(buf, "DL-32 > [%8s][%12s] .. [%12s][%16s] > SoloDL", inputt.c_str(), inputf.c_str(), outputf.c_str(), outputt.c_str());

      info(buf);
    }
    info(".");
  }

  /* all done */

  return true;
}

/**
 *
 * patch() - swap the order of input to output channels.  If we
 * swap channel 1 and channel 2, then whatever was being sent to
 * the Solo DL on channel 1...now happens on channel 2, and vice
 * versa.  If you need to undo any reorderings you mape, you can
 * use patchReset() to go back to what it was when you loaded the
 * ECU Bridge (i.e. whatever is in the .ini file).  You can use
 * patchDefault() to have 1:1, 2:2, etc.
 *
 * NOTE: both channel values must be in the range 1..15 and both
 * channels refer to the output side; its the output side that
 * stays consistent while you choose which inputs to apply to
 * the outputs.
 *
 * @param chan1 int - the first channel to swap
 *
 * @param chan2 int - the other channel you are swapping with.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::patch(int chan1, int chan2) {

  /* check parameters */

  if(!isReady()) {
    error("patch() - object not ready.");
    return false;
  }

  if((chan1 < 1) || (chan1 > 15)) {
    error(string("patch() - channel #1 must be 1..15: ") + to_string(chan1));
    return false;
  }

  if((chan2 < 1) || (chan2 > 15)) {
    error(string("patch() - channel #1 must be 1..15: ") + to_string(chan1));
    return false;
  }

  /* swap 'em */

  int dstA = chan1;
  int srcA = patchTableInverted[dstA];

  int dstB = chan2;
  int srcB = patchTableInverted[dstB];

  patchTableInverted[dstB] = srcA;
  patchTableInverted[dstA] = srcB;

  patchTable[srcB] = dstA;
  patchTable[srcA] = dstB;

  /* all done */

  return true;
}

/**
 *
 * patchReset() - reset the patch table whatever it was
 * in the .ini file when we loaded the ECU Bridge.  That
 * is just undo any live patch changes.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::patchReset(void) {

  if(!isReady()) {
    error("patchReset() - object not ready.");
    return false;
  }

  for(int zz=1; zz <= CMMaxChannels; zz++) {
    patchTable[zz] = patchTableOrig[zz];
  }

  /* make sure the inverse of the patch table is up to date */

  invertPatchTable();

  /* all done */

  return true;
}

/**
 *
 * patchDefault() - reset the patch table to input 1 goes
 * to output 1, input 2 goes to output 2, etc.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::patchDefault(void) {

  if(!isReady()) {
    error("patchDefault() - object not ready.");
    return false;
  }

  for(int zz=1; zz <= CMMaxChannels; zz++) {
    patchTable[zz] = patchTableDefault[zz];
  }

  /* make sure the inverse of the patch table is up to date */

  invertPatchTable();

  /* all done */

  return true;
}

/**
 *
 * clear() - clear everything out, prepare for a clean start.
 *
 * @return bool exactly false on error.
 *
 */

bool ChannelManager::clear(void) {

  info("resetting...");

  /* clear out the transforms */

  {
    for(int i=0; i<(CMMaxChannels+1); i++) {

      if(inputTrans[i] != NULL) {
        delete inputTrans[i];
        inputTrans[i] = NULL;
      }
      if(inputFilter[i] != NULL) {
        delete inputFilter[i];
        inputFilter[i] = NULL;
      }
      if(outputFilter[i] != NULL) {
        delete outputFilter[i];
        outputFilter[i] = NULL;
      }
      if(outputTrans[i] != NULL) {
        delete outputTrans[i];
        outputTrans[i] = NULL;
      }
    }
  }

  /* reset the patch table */

  {
    for(int i=0; i<(CMMaxChannels+1); i++) {

      patchTable[i]        = 0;
      patchTableOrig[i]    = 0;
      patchTableDefault[i] = i;

    }
  }

  info("cleared.");

  unReady();

  /* all done */

  return true;
}

/**
 *
 * tranform() - given an inpout value, treat it as if it had come from the DL-32,
 * and process it through our channel mapping and return the output which would
 * be normally sent to the Solo DL.  No output is actually sent to the Solo DL and
 * not input is actually read from the DL-32. This is a "dry run" of what the
 * given chennel will do to data it is given.
 *
 *
 * @param channel int - the channel to process, must be in the range 1..15,
 * this is the output channel (which may be patched to some other input
 * channel than the normal mapping of 1:1, 2:2, etc.).
 *
 * @param input unsigned int - the raw data input (as if it came from DL-32).
 *
 * @param output unsigned int - the final output, that would normally be sent
 * to the Solo DL.
 *
 * @return bool - exactly false on error.
 *
 */

bool ChannelManager::transform(int channel, unsigned int input, unsigned int & output) {

  /* check the parameters */

  if(!isReady()) {
    error("tranform() - object not ready.");
    return false;
  }

  if((channel < 1) || (channel > 15)) {
    error(string("tranform() - channel # must be 1..15: ") + to_string(channel));
    return false;
  }

  /* do it! */

  int dst = channel;
  int src = patchTableInverted[dst];

  /*
   * normalize
   *
   */

  unsigned int normal = inputFilter[src]->y(inputTrans[src]->y(input));

  /*
   * convert for SoloDL, we have to do the inverse of what
   * the AIM  Protocol will do, so that the SoloDL actually
   * sees the data we want it to.
   *
   */

  output = outputTrans[dst]->inverse(outputFilter[dst]->y(normal));

  if(false) {

    static char buf[2048];

    sprintf(buf, "chan %d r: %d rt: %d rf: %d of: %d ot: %d",
            channel,
            input,
            inputTrans[src]->y(input),
            inputFilter[src]->y(inputTrans[src]->y(input)),
            outputFilter[dst]->y(normal),
            outputTrans[dst]->inverse(outputFilter[dst]->y(normal)));

    info(string("tranform() ") + buf);
  }

  /* all done */

  return true;
}

/**
 *
 * load() - taking raw inputs from sampling device (i.e. DL-32),
 * and transofrm/filter that into normal data.  For example if you
 * expect a value of 1 you get 1.  Finally filter/transform the
 * data into the the final output that can go directly to the SoloDL
 * without any further processing.
 *
 * @param input array of integers - this is the input (left) side
 * of the bridge.  The data is the raw data from the DL-32 or whatever
 * else we are using as the input.
 *
 * @param normal array of integers - after any transofrming/filtering
 * of the input this is our official data, the normal human readable
 * values.  If the DL-32 required any fancying transforming of its sampling
 * data we've already done that, and this data is where we get 1 if we
 * are expecting 1.
 *
 * @param output array of integers - after any transforming/filtering
 * of the normal data,  this is our official data to send to the SoloDL
 * without any further processing.
 *
 * @return bool - exactly false if there is some kidn of error.
 *
 */

bool ChannelManager::load(unsigned int *input,
                          unsigned int *normal,
                          unsigned int *output) {

  normal[0] = 0;
  output[0] = 0;

  for(int i=1; i<=CMMaxChannels; i++) {

    /* normalize */

    normal[i] = inputFilter[i]->y(inputTrans[i]->y(input[i]));

    /*
     * convert for Solo DL, we have to do the inverse of what
     * the AIM  Protocol will do, so that the SoloDL actually
     * sees the data we want it to.
     *
     * NOTE: at the same time we have to pick which input side
     * of the channel to use, because the outputs have to stay
     * in the order they are in (per AIM protocol) we use an
     * inverse map to pick off which left side to use to push
     * to the right side.
     *
     */

    int src = patchTableInverted[i];

    output[i] = outputTrans[i]->inverse(outputFilter[i]->y(normal[src]));

    if(false) {

      static char buf[2048];

      sprintf(buf, "chan %d r: %d rt: %d rf: %d of: %d ot: %d",
              i,
              input[i],
              inputTrans[i]->y(input[i]),
              inputFilter[i]->y(inputTrans[i]->y(input[i])),
              outputFilter[i]->y(normal[patchTable[i]]),
              outputTrans[i]->inverse(outputFilter[i]->y(normal[src])));

      info(string("load() ") + buf);
    }
  }

  /* all done */

  return true;
}

/* standard destructor */

ChannelManager::~ChannelManager() {

  info("Shutting down...");

  clear();
}
