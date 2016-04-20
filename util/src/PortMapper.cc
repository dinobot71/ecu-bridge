#include "PortMapper.hh"

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

bool PortMapper::findFT4232H(vector<string> & devices) {

  /*
   * run 'dmesg' to look for the "attached" messages
   * that are generated when the ports get detected and
   * associated with /dev/<device>.  This is the easiest
   * way to make a direct mapping, and its usually in
   * predictable order.
   *
   */

  vector<string> output;
  int status;

  if(!exec("/bin/dmesg", output, status)) {

    string text;
    implode(output, "\n", text);
    error(string("findFT4232H() - could not invoke dmesg (") + to_string(status) + string("), output was: ") + text);

    return false;
  }

  /* walk through the output looking for the FTDI attach lines... */

  vector<string> terminals;

  regex  goodLine("\\sFTDI\\s+(?:.*)\\s+attached\\s+to\\s+(\\S+)", regex_constants::ECMAScript);
  smatch sm;

  for(auto & line : output) {

    if(regex_search(line, sm, goodLine)) {

      string terminal = sm[1];
      terminals.push_back(terminal);

      /*
       * we want the tail end, whatever the most recent oens are,
       * since the cable may have been unplugged and plugged back
       * in.
       *
       */

      if(terminals.size() > 4) {
        terminals.erase(terminals.begin());
      }
    }
  }

  /* there was attach/detach/attach, just keep the most recent 4 (the ones at the end */

  if(terminals.size() < 4) {

    error("findFT4232H() - can't find USB/Serial ports.");
    return false;
  }

  /*
   * so now we have the /dev/<name> values and they are in order
   * and we know the how to connect them to our devices, because
   * we have the device slots.
   *
   */

  for(auto & devEnum : deviceEnums) {

    string device = devEnum.first;
    Device code   = devEnum.second;

    if(cableOrder.count(code) == 0) {

      warning(string("findFT4232H() - Can't find cable for device: ") + device);
      continue;
    }

    int cable         = cableOrder[code];
    string terminal   = terminals[cable-1];

    devicePaths[code] = string("/dev/") + terminal;
  }

  /*
   * all done, we can now look up by Enum what the terminal
   * is for our desired device.
   *
   */

  return true;
}

/**
 *
 * configure() - (re)configure the port mapping from the
 * config.ini file.  Any of our features that use our
 * configuration file must support the configure method so
 * we can do a "reload" on our daemon.
 *
 */

bool PortMapper::configure(void) {

  info("configure() - reconfiguring...");

  unReady();

  devicePaths.clear();
  cableOrder.clear();
  deviceEnums.clear();

  /*
   * figure out our general configuration
   *
   */

  IniFile ini = ConfigManager::instance();

  if(!ini.isReady()) {

    /* we can't read/find the configuration file? */

    error("configure() - can not load configuration manager.  Missing config.ini file?");
    return false;
  }

  vector<string> devices;

  string cable = ini.getValue("PortMapper", "cable_type");
  string value = ini.getValue("PortMapper", "devices");

  explode(value, " ,\t", devices);

  if(devices.size() == 0) {

    /* devices? */

    error("configure() - no apparent devices in configuration.");
    return false;
  }

  cable = trim(strtolower(cable));

  if(cable.empty()) {

    /* no usb/serial cable? */

    error("configure() - no apparent cable type configuration.");
    return false;
  }

  /*
   * map the devices to known enums, and make sure device names
   * are sanitized.
   *
   */

  for(auto & devName : devices) {

    if(devName == "dl32") {

      deviceEnums[devName] = Device::DL32;

    } else if(devName == "solodl") {

      deviceEnums[devName] = Device::SOLODL;

    } else {

      warning(string("configure() - unknown device: ") + devName);

    }

  }

  /* map out the device slots */

  for(auto & devName : devices) {

    value = ini.getValue(devName, "usb_slot");
    if(is_numeric(value)) {

      int slot = (int)strtol(value.c_str(), NULL, 10);

      if((slot < 1)||(slot>4)) {
        warning(string("configure() - device (") + devName + ") has out of range usb_slot (ignoring).");
        continue;
      }

      /* make sure we don't have conflicting slots */

      if(cableOrder.count(deviceEnums[devName])) {

        warning(string("configure() - device (") + devName + ") already has a usb_slot (ignoring).");
        continue;
      }

      for (map<Device,int>::iterator it = cableOrder.begin(); it != cableOrder.end(); ++it) {

        if (it->second == slot) {

          /* this cable is already in use! */

          error(string("configure() - device (") + devName + string(") is trying to use a cable (") + to_string(slot) + string(")already in use."));
          return false;
        }
      }

      cableOrder[deviceEnums[devName]] = slot;

    } else {

      error(string("configure() - bad usb_slot value (") + value + string(") for device: ") + devName);
      return false;
    }
  }

  if(cableOrder.size() == 0) {
    error("No cable ordering!");
    return false;
  }

  /*
   * to figure out how things are attached we need to
   * configure based on the cable being used, by default
   * it should be our FTDI quad cable.
   *
   */

  if(cable == "ft4232h") {

    info("configure() - scanning for ft4232h cable devices...");

    if(!findFT4232H(devices)) {
      error("configure() - can not map out FTDI devices.");
      return false;
    }

  } else {

    error("configure() - no valid cable type configured, can't map devices.");
    return false;
  }

  /*
   * at this point the devices have been mapped and we should
   * be ready for use.
   *
   */

  makeReady();

  info("Serial port map: ");

  info(".");

  for(auto & devName : devices) {

    string path = devicePaths[deviceEnums[devName]];

    info(string(" . ") + devName + string(" => ") + path);
  }

  info("configure() - ready.");

  /* all done */

  return true;
}








