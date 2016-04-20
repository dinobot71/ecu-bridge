#include "SoloDLPort.hh"
#include "PortMapper.hh"

#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <fcntl.h>
#include <time.h>
#include <sys/ioctl.h>
#include <sys/select.h>
#include <sys/time.h>
#include <sys/types.h>

INITIALIZE_EASYLOGGINGPP

int timevalSubtract(struct timeval *result, struct timeval *x, struct timeval *y) {

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

int main(int argc, const char* argv[]) {

  /* configure logging */

  if(!LogManager::configure()) {
    cout << "[FAIL] can not configure logging." << endl;
    return 1;
  }

  /* find the device path */

  PortMapper pm;

  if(!pm.isReady()) {
    cout << "[FAIL] can not find USB/Serial Ports: " << pm.getError() << endl;
    return 1;
  }

  string device = pm.getDevice(Device::SOLODL);

  if(device.empty()) {
    cout << "[FAIL] can not find DL-32 device." << endl;
    return 1;
  }

  /* open it */

  SoloDLPort port(device);

  if(!port.isReady()) {
    cout << "[FAIL] can not open Solo DL: " << port.getError() << endl;
    return 1;
  }

  /*
   * write some well deifned data for a few seconds so we
   * can check with Race Studio Online that the Solo DL
   * is seeing the data we think it is.
   *
   */

  cout << "generating data..." << endl;

  time_t   t1  = time(NULL);
  int    sent  = 10 * 60; /* 1 minute of send */
  int oddeven  = 0;

  char buf[1024];
  unsigned int samples[SoloDLChannelMax+1];

  timeval tv1;
  timeval tv2;
  timeval tv3;
  timeval result;

  if(gettimeofday(&tv1, NULL) != 0) {
    cout << "[FAIL] ERROR: can't get time of day (0)." << endl;
    return 1;
  }

  float cycleTime = 0.0;

  while(sent > 0) {

    tv3 = tv1;

    if(gettimeofday(&tv1, NULL) != 0) {
      cout << "[FAIL] ERROR: can't get time of day (A)." << endl;
      return 1;
    }

    /*
     * track the cycle time in ms, to make sure we are hitting
     * our 100ms mark.
     *
     */

    if(timevalSubtract(&result, &tv1, &tv3)) {
      cycleTime = result.tv_usec * -1;
    } else {
      cycleTime = result.tv_usec;
    }

    cycleTime /= 1000.0;

    /* figure out the samples */

    for(int zz=1; zz<=SoloDLChannelMax; zz++) {
      samples[zz] = (unsigned int)(100 + (oddeven * 100));
    }

    /* send the samples (do the work) */

    if(!port.writeSamples(samples)) {
      cout << "[FAIL] ERROR: can't write samples: " << port.getError() << endl;
      return 1;
    }

    sprintf(buf, "%03d (%03.2f): ", sent, cycleTime);
    sprintf(buf+strlen(buf), "%6d %6d %6d %6d %6d ", samples[1],  samples[2],  samples[3],  samples[4],  samples[5]);
    sprintf(buf+strlen(buf), "%6d %6d %6d %6d %6d ", samples[6],  samples[7],  samples[8],  samples[9],  samples[10]);
    sprintf(buf+strlen(buf), "%6d %6d %6d %6d %6d ", samples[11], samples[12], samples[13], samples[14], samples[15]);

    cout << buf << endl;

    /* align ourselves to send the next one on the next 100ms tick */

    /*
     * send data every 100ms (10hz), the above work used some
     * time, so figure out where are now (between 100ms ticks)
     * and sleep for the remainder
     *
     */

    if(gettimeofday(&tv2, NULL) != 0) {
      cerr << "ERROR: can't get time of day (B)." << endl;
      return 1;
    }

    unsigned long delta = 0;

    if(timevalSubtract(&result, &tv2, &tv1)) {
      delta = result.tv_usec * -1;
    } else {
      delta = result.tv_usec;
    }

    unsigned long cycle    = 100 * 1000;
    unsigned long sleepFor = cycle - delta;

    if(sleepFor < 0) {

      /* we took too long, no sleeping! */

      sleepFor = 0;
    }

    /* pause until we are ready to go again... */

    usleep(sleepFor);

    /* update our tick */

    sent--;

    /* flip pattern every 5sec */

    if((sent % 50) == 0) {

      if(oddeven) {

        oddeven = 0;
      } else {

        oddeven = 1;
      }
    }
  }

  cout << "." << endl;

  return 0;
}
