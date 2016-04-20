#include "USBCable.hh"

/**
 *
 * findCable() - helper to detect if the cable is present or not
 *
 * @return bool exactly true if the cable is plugged in.
 *
 */

bool USBCable::findCable(void) {

  connected = false;

  /*
   * scan the USB devices, pick up our known USB cable
   * types, right now it should just be one, the FTDI
   * quad cable.
   *
   */

  struct udev_enumerate *enumerate = udev_enumerate_new(udev);
  udev_enumerate_add_match_subsystem(enumerate, "usb");
  udev_enumerate_scan_devices(enumerate);

  struct udev_list_entry *devices = udev_enumerate_get_list_entry(enumerate);

  if(devices == NULL) {
    error("findCable() - can't get USB device list.");
    return false;
  }

  /* walk over the devices looking for our cable */

  struct udev_list_entry *entry = NULL;
  struct udev_device *dev       = NULL;
  const char *path              = NULL;

  udev_list_entry_foreach(entry, devices) {

    path = udev_list_entry_get_name(entry);
    dev  = udev_device_new_from_syspath(udev, path);

    if(dev == NULL) {

      /* bad device? */

      continue;
    }

    dev = udev_device_get_parent_with_subsystem_devtype(dev, "usb", "usb_device");

    if(dev == NULL) {

      /* no parent? */

      continue;
    }

    /* grab the VID:PID, if they match our cable, we found it :) */

    const char *vid, *pid;

    vid = udev_device_get_sysattr_value(dev, "idVendor");
    pid = udev_device_get_sysattr_value(dev, "idProduct");

    if(strcmp(vid, VENDOR_FT4232H_ID)||strcmp(pid, PRODUCT_FT4232H_ID)) {

      /* no match */

      udev_device_unref(dev);
      continue;
    }

    /*
     * ok we found it, there will actually be 4 entries (its a quad cable!),
     * but we only need to confirm that its there.
     *
     */

    cabledetails.serial       = udev_device_get_sysattr_value(dev, "serial");
    cabledetails.version      = udev_device_get_sysattr_value(dev, "version");
    cabledetails.manufacturer = udev_device_get_sysattr_value(dev, "manufacturer");
    cabledetails.product      = udev_device_get_sysattr_value(dev, "product");
    cabledetails.removable    = udev_device_get_sysattr_value(dev, "removable");

    udev_device_unref(dev);

    connected = true;

    break;
  }

  /* Free the enumerator object */

  udev_enumerate_unref(enumerate);

  return connected;
}

/**
 *
 * configure() - (re)configure, make self ready
 * if possible.
 *
 */

bool USBCable::configure(void) {

  info("configure() - setting up...");

  /* force a refresh */

  clear();

  /*
   * setup for monitoring USB events
   *
   */

  udev = udev_new();
  if(udev == NULL) {
    error("configure() - can't create udev library object.");
    return false;
  }

  mon = udev_monitor_new_from_netlink(udev, "udev");
  udev_monitor_filter_add_match_subsystem_devtype(mon, "usb", NULL);
  udev_monitor_enable_receiving(mon);

  fd = udev_monitor_get_fd(mon);

  if(fd < 0) {
    error("configure() - can't get USB descriptor.");
    return false;
  }

  /* detect the cable (set 'connected' status) */

  findCable();

  /*
   * even if the cable isn't plugged in, we are ready to
   * monitor the situation.
   *
   */

  makeReady();

  info("configure() - ready.");

  /* all done! */

  return true;
}

/**
 *
 * getEvent() - after waiting for USB events, one is
 * ready, go and get it, passing back the kind of
 * event that happened.  We don't need a lot of detail
 * its a cable, cable goes in, cable goes out.
 *
 * @param eventKind - an enum we pass back to indicate
 * the kind of event that occurred.
 *
 * @return bool - exactly false on error.
 *
 */

bool USBCable::getEvent(USBEvent & eventKind) {

  eventKind = USBEvent::UNKNOWN;

  struct udev_device *dev = udev_monitor_receive_device(mon);

  if(dev == NULL) {
    error("getEvent() - can't fetch monitor device.");
    return false;
  }

  const char *ptr = udev_device_get_action(dev);

  if(ptr == NULL) {
    error("getEvent() - action is NULL.");
    return false;
  }

  string action = ptr;

  udev_device_unref(dev);

  if(action.empty()) {
    error("getEvent() - action is missing.");
    return false;
  }

  info(string("getEvent() - got USB event: ") + action);

  /* what happened? */

  if((action == "add")||
     (action == "remove")||
     (action == "change")||
     (action == "online")||
     (action == "offline")||
     (action == "add")) {

    if(action == "add") {
      eventKind = USBEvent::ADD;
    } else if(action == "remove") {
      eventKind = USBEvent::REMOVE;
    } else if(action == "change") {
      eventKind = USBEvent::CHANGE;
    } else if(action == "online") {
      eventKind = USBEvent::ONLINE;
    } else if(action == "offline") {
      eventKind = USBEvent::OFFLINE;
    }

    /* rescan for our cable to see if we are connected or disconnected */

    findCable();

    /* all done */

    return true;
  }

  error(string("getEvent() - unrecognized USB event: ") + action);
  return false;
}

/**
 *
 * waitForEvents() - wait for something to happen
 * (USB connect, disconnect etc.)
 *
 */

bool USBCable::waitForEvents(int maxRetry) {

  if(!isReady()) {
    error("waitForEvents() - object is not ready.");
    return false;
  }

  int retryCount = 0;

  while(true) {

    fd_set fds;
    struct timeval tv;

    FD_ZERO(&fds);
    FD_SET(fd, &fds);

    tv.tv_sec  = readTimeout;
    tv.tv_usec = 0;

    int status = select(fd+1, &fds, NULL, NULL, &tv);

    if(status == 0) {

      /* time out, do we keep trying? */

      retryCount++;

      if(maxRetry > 0) {

        warning(string("waitForEvents() - select timed out (") + to_string(readTimeout) + ").");

        if(retryCount >= maxRetry) {
          error(string("waitForEvents() - maximum # of timeouts reached (") +to_string(retryCount) + ").");
          return false;
        }
      }

      /* try, try again */

      continue;
    }

    if(status < 0) {
      error(string("waitForEvents() - select() error: ") + strerror(errno));
      return false;
    }

    if(FD_ISSET(fd, &fds)) {

      /* something happened! */

      break;
    }
  }

  /* all done */

  return true;
}

/**
 *
 * clear() - get ready to configure fresh, or shutdown.
 *
 */

bool USBCable::clear(void) {

  unReady();

  if(udev != NULL) {
    udev_unref(udev);
    udev = NULL;
  }

  if(mon != NULL) {

    /* we don't have to free it */

    mon = NULL;
  }

  fd = -1;

  connected = false;

  cabledetails.serial       = "";
  cabledetails.version      = "";
  cabledetails.manufacturer = "";
  cabledetails.product      = "";
  cabledetails.removable    = "";

  return true;
}
