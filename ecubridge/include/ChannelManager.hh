#ifndef CHANNELMANAGER_HH
#define CHANNELMANAGER_HH

#include "Object.hh"

/* include input/output transformers */

#include "AIMAirChargeTempTransform.hh"
#include "AIMBattVoltTransform.hh"
#include "AIMErrorFlagTransform.hh"
#include "AIMExhTempTransform.hh"
#include "AIMFuelPressTransform.hh"
#include "AIMFuelTempTransform.hh"
#include "AIMGearTransform.hh"
#include "AIMLambdaTransform.hh"
#include "AIMManifPressTransform.hh"
#include "AIMOilPressTransform.hh"
#include "AIMOilTempTransform.hh"
#include "AIMRPMTransform.hh"
#include "AIMThrotAngTransform.hh"
#include "AIMWaterTempTransform.hh"
#include "AIMWheelSpeedTransform.hh"
#include "DL32Chan1Transform.hh"
#include "DL32Chan2Transform.hh"
#include "DL32Chan3Transform.hh"
#include "DL32Chan4Transform.hh"
#include "DL32Chan5Transform.hh"
#include "ManualTransform.hh"
#include "NullTransform.hh"
#include "PassthroughTransform.hh"

/**
 *
 * ChannelManager - handles mapping DL-32 Data to the SoloDL through
 * "channels".  When we receive data from DL-32, we "load" it into
 * normalized and output data via this manager, and then whenever
 * we are ready to send to the SoloDL we send loaded data.  The act
 * of loading passing the data through whatever transforms, filters
 * or channel re-mapping needs to happen.
 *
 * Configuration is done in the config.ini file or at run time you
 * can request to change an input or output filter, or patch the
 * channel ordering.
 *
 *   ECU Bridge - this is daemon, the main controller.  Everything in
 *   this section is for configuring how the daemon works. The ECU Bridge
 *   has 15 channels, defined below.  If you need to patch different
 *   things, you can use the "patch order" to redefine which stuff on the left
 *   goes to which stuff on the right.   You can't redefine the transforms
 *   on the left or what is on the righ though; they are defined by our
 *   expectation of devices/protocol connected.
 *
 *
 *     chan #  transrom  filter  patch order  filter   transform
 *     ---------------------------------------------------------
 *     chan 1  [dl32-1]  [pass]  1            [pass]   [rpm]
 *     chan 2  [dl32-2]  [pass]  2            [pass]   [wheelspeed]
 *     chan 3  [dl32-3]  [pass]  3            [pass]   [oilpress]
 *     chan 4  [dl32-4]  [pass]  4            [pass]   [oiltemp]
 *     chan 5  [dl32-5]  [pass]  5            [pass]   [watertemp]
 *     chan 6  [null]    [null]  6            [pass]   [fuelpress]
 *     chan 7  [null]    [null]  7            [pass]   [battvolt]
 *     chan 8  [null]    [null]  8            [pass]   [throtang]
 *     chan 9  [null]    [null]  9            [pass]   [manifpress]
 *     chan 10 [null]    [null]  10           [pass]   [airchargetemp]
 *     chan 11 [null]    [null]  11           [pass]   [exhtemp]
 *     chan 12 [null]    [null]  12           [pass]   [lambda]
 *     chan 13 [null]    [null]  13           [pass]   [fueltemp]
 *     chan 14 [null]    [null]  14           [pass]   [gear]
 *     chan 15 [null]    [null]  15           [pass]   [errorflag]
 *
 *   DL-32 data comes in on the left and goes out on the right to the solodl
 *   along with possibly other data.  You can fitler both at the input and
 *   the output side. By chaning the patch order you can rewire which things
 *   on the left go to which things on the right.  If you want dl31-1 to go
 *   to oilpress, then you have ot make its patch order be 3...and move 1
 *   somewhere else (i.e. now dl32-3 would be rpm).
 *
 */

enum CMMaxChannels {CMMaxChannels=15};

class ChannelManager : public Object {

  private:

    /**
     *
     * inputTrans - initial transform of incoming
     * data form the DL-32.
     *
     */

    DataTransformer *inputTrans[CMMaxChannels+1];

    /**
     *
     * inputFilter - filter incoming data
     *
     */

    DataTransformer *inputFilter[CMMaxChannels+1];

    /**
     *
     * patchTable - remap input side to output side
     * if we want to rewire things.
     *
     */

    int patchTable[CMMaxChannels+1];
    int patchTableInverted[CMMaxChannels+1];
    int patchTableOrig[CMMaxChannels+1];
    int patchTableDefault[CMMaxChannels+1];

    /**
     *
     * outputFilter - filter before solodl processing
     *
     */

    DataTransformer *outputFilter[CMMaxChannels+1];

    /**
     *
     * outputTrans - final transformation before solodl output
     *
     */

    DataTransformer *outputTrans[CMMaxChannels+1];

    /**
     *
     * invertPatchTable() - internal helper function, when we swap channels
     * around, we have to keep an inverted map, so we can look up the linkage
     * quickly, without have to scan the patch table for what goes where.
     *
     */

    bool invertPatchTable(void);

  protected:

  public:

    /* standard constructor */

    ChannelManager(void);

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

    bool configure(void);

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

    bool load(unsigned int *input,
              unsigned int *normal,
              unsigned int *output);

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

    bool transform(int channel, unsigned int input, unsigned int & output);

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

    bool setOutputFilter(int chan, DataTransformer *filter);

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

    bool setInputFilter(int chan, DataTransformer *filter);

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

    bool channelMap(string & mapping, bool terse=false);

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
     * @param chan1 int - the first channel to swap
     *
     * @param chan2 int - the other channel you are swapping with.
     *
     * @return bool - exactly false on error.
     *
     */

    bool patch(int chan1, int chan2);

    /**
     *
     * patchReset() - reset the patch table whatever it was
     * in the .ini file when we loaded the ECU Bridge.  That
     * is just undo any live patch changes.
     *
     * @return bool - exactly false on error.
     *
     */

    bool patchReset(void);

    /**
     *
     * patchDefault() - reset the patch table to input 1 goes
     * to output 1, input 2 goes to output 2, etc.
     *
     * @return bool - exactly false on error.
     *
     */

    bool patchDefault(void);

    /**
     *
     * clear() - clear everything out, prepare for a clean start.
     *
     * @return bool exactly false on error.
     *
     */

    bool clear(void);

    /* standard destructor */

    virtual ~ChannelManager();

};





#endif
