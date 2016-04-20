# ECU Bridge (Chump Car Framework)
## Introduction
The ECU (Engine Control Unit) Bridge is a flexible means of bringing sensor data from legacy or non-standard equipment to modern data loggers and analysis tools.  A Raspberry PI is used as a flexible and affordable way to physically wire the old equipment with the new.  Software onboard the Raspberry PI (the ECU Bridge software) handles the details of mapping the legacy equipment inputs to the modern equipment outputs. Together the Raspberry PI and the ECU Bridge software are what we mean by “ECU Bridge”.

The ECU Bridge can even serve as a software defined ECU – potentially you can simulate data that would come from another engine, or you can bring together sensors to define your own ECU without actually having an ECU.  The end result is that you can flexibly do data logging and analysis for equipment that normally would not be compatible at all with modern data loggers and analysis software.

In our project we are bringing in data from a Honda ECU via an analog/digital data logger (DL-32); from a  “Chump Car” racing league car that isn’t compatible with modern data loggers.  The data is then sent on to an AIM Solo DL and finally to an on dash SmartyCam camera.

While our project is specific to this equipment, the ECU Bridge is designed to be readily extended to other kinds of equipment or do other kinds of ECU translations.  

![alt tag](https://raw.githubusercontent.com/dinobot71/ecu-bridge/master/readme/over1.jpg)

## Future Integrations

Currently our focus is on getting sensor and ECU data from an old Honda car to the SoloDL and SmartyCam.  However, the ECU Bridge could easily be extended further:

- In one of the remaining RS-232 ports, send data out to a radio link in addition to sending to the SoloDL port.  So you could have a live feed from the car to the paddocks and be making real-time adjustments based on car performance.

- In one of the remaining RS-232 ports hook up other sensor modules.  As long as the sensor or alternative ECU supports RS-232 in some fashion, you are good to go!

## Future Integrations

Currently our focus is on getting sensor and ECU data from an old Honda car to the SoloDL and SmartyCam.  However, the ECU Bridge could easily be extended further:

- In one of the remaining RS-232 ports, send data out to a radio link in addition to sending to the SoloDL port.  So you could have a live feed from the car to the paddocks and be making real-time adjustments based on car performance.

- In one of the remaining RS-232 ports hook up other sensor modules.  As long as the sensor or alternative ECU supports RS-232 in some fashion, you are good to go!

## Physical Wiring

The motor vehicle industry commonly uses a variation of RS-232 protocol/wiring called CAN or CAN Bus.  Essentially its RS-232 but specialized for cars and other vehicles. Fortunately the equipment we are integrating doesn’t use full RS-232 capabilities; no modem control lines are used for example, its just raw RX/TX lines.  So, as long as you can integrate with an RS-232 connector (basically a D-Sub 9-pin connector), and a UART…you are good to go.  
In our set we use a USB to RS-232 conversion cable that allows us to work with 4 separate RS-232 connectors via a single USB plug.  We currently use only 2 of them:

1. Input from the DL-32.  
2. Output to the Solo DL.

The USB conversion cable we use is the FT4232H, which uses a UART chipsets that the Linux kernel  has very good support for already. So connecting to the external devices requires very little effort; just make sure they are wired to the RX/TX lines of D-Sub 9 pin connectors, and plug them into the USB conversion cable.  After that the ECU Bridge software takes over.  You can send and receive on any of the RS-232 ports, but obviously the device must support either operation, and  you must send or receive data with the appropriate protocol.
 
Fortunately both the DL-32 and SoloDL have documented specs for the protocol they use.  Nether one is very complicated, but the SoloDL has some timing requirements; the various data channels must be sent with a particular frequency.  Conversely the DL-32 has all its channels read at once.
NOTE: you will also need power  A 5V DC adaptor ending with a micro-usb plugin for the RPI2, and for the DL-32, you’ll need a 12V DC power supply; something that is normally used for CB radios or the equivalent of a car batter.  Such power supplies are easy to find at Amazon or your local electronics store. Personally I used a Pyramid PS3 3-Amp 12-Volt Power Supply with a chopped molex connector (that I wasn’t using from an old computer)

## Raspberry PI Add-on Hardware

A couple of add-ons are used to make life easier when working with the RPI2; neither takes away from system performance or available GPIO pins.

1. PI-Face Real Time Clock (RTC) – a batter backed hardware clock that allows us to have consistent time stamping onboard the RPI2.  This is critical for the onboard data logging and any debugging that happens via the logs.

2. WI-PI Wi-Fi Adaptor – allows for wireless access to the RPI2.  This in turn allows you to work with the RPI2 using a smart phone or tablet.  If you are at the track you may not allays have a laptop handy!

3. FT4232H Quad RS-232 / USB cable.  This cable allows us to not only connect to multiple RS-232 ports while using a single USB port, but also handles voltage conversion/regulating between RS-232 levels and TTL (5V DC) used by the Raspberry PI.  Very handy!

## ECU Bridge Software

The high level view of the software running on the RPI2:

![alt tag](https://raw.githubusercontent.com/dinobot71/ecu-bridge/master/readme/over2.jpg)

From above you can see that we have 3 (three) software daemons:

1. ecubridge – this is the main controller.  In addition to passing data from DL-32 to the Solo DL, it will listen for user commands on a TCP port (which may come from the command line or the Web GUI), and will also listen to the USB Bus so it can react intelligently if the USB cable isn’t plugged in yet, or if the USB cable is re-connected.

2. ecudatalogger – listens to the ecubridge (via multi-cast UDP) to capture the raw, normal and output data of the ecubridge as its transcoded (live) without interrupting or slowing down the ECU Bridge.  Once we have the data, we pass on to RRD for actual storage and to later generate graphs etc.  Note though we are currently configured to only capture the most recent 24 hours worth of data, so after a race, be sure to power off the RPI2 so that it doesn’t keep recording and wipe out the race data.

3. RRD Tool – this is the standard Open Source tool for data logging.  Even though it doesn’t support millisecond level time resolution (it only goes down to 1second), we just average our recorded data (at 10hz) to 1hz and log that.  The purpose of the on board data logging is for analyzing and debugging the transcoding of data not the actual performance of the race car, so a resolution of 1sec is acceptable.  Other onboard data loggers were considered, but basically RRD is the best in breed and easiest / fastest to get up and running with.
These are all started automatically when the RPI2 is powered on.  They each have their own configuration and logging (see the System Setup chapter).  You can fully configure them as needed, and debug them using their log files.   

## Transcoding

To allow the ECU Bridge to be easily extended, flexibly adapt to different kinds of inputs or work with an output other than the Solo DL, or potentially act as a software defined ECU, we’ve introduced the idea of a virtual channel:

![alt tag](https://raw.githubusercontent.com/dinobot71/ecu-bridge/master/readme/over3.jpg)

There is a virtual channel for each data channel the output device supports.  In our project it’s the Solo DL, so there are 15 channels, ranging in kind from RPM, to water temperature to error flag.   Other devices could potentially have different kinds of channels.

Virtual channels also give us a lot of flexibility, by defining in software what transformations happen on the input to the channel and the output of the channel, we can easily adapt to connecting different kinds of devices, or having different ways of scaling / interpreting the inputs and outputs.    For example if the DL-32 was replaced with some other means of monitoring the Honda ECU in an analog way…we might have to change how we scale the input values to get the “normal” values we expect from the gauges on the car dashboard.  

Also, at run time we may decide that we need to change which inputs go to which outputs…but we may not want to have to physically rewire the input setup.  To allow for this kind of hot wiring, we support the idea of source/destination patching.  In the middle of the virtual channel, we allow the normal data to be redirected from one virtual channel to another before it goes to the output side (filtering and transformation), and on to the Solo DL.  

![alt tag](https://raw.githubusercontent.com/dinobot71/ecu-bridge/master/readme/over4.jpg)

Simply by “patching” channels we can hot wire where input data goes.  No need to get out the ‘ol tool box 
