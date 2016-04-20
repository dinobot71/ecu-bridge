# commoon variables 

CC        = g++
CFLAGS    = -g -I. -I./util/include -I./ecubridge/include -I./ecudatalogger/include --std=c++11
LD        = ld
LDFLAGS   = -L. -L./obj
AR        = ar
ARFLAGS   = r

UTIL_HDRS = \
	util/include/util.hh \
	util/include/IniFile.hh \
	util/include/ConfigManager.hh \
	util/include/LogManager.hh \
	util/include/Object.hh \
	util/include/RS232Port.hh \
	util/include/PortMapper.hh \
	util/include/DataTapWriter.hh \
	util/include/DataTapReader.hh

UTIL_SRCS =

LOGGER_HDRS = \
  ecudatalogger/include/RRDConnector.hh
  
LOGGER_SRCS = 

LOGGER_OBJ = \
  obj/RRDConnector.o
  
ECU_HDRS  = \
	ecubridge/include/ECUBridge.hh \
	ecubridge/include/AIMAirChargeTempTransform.hh \
	ecubridge/include/AIMBattVoltTransform.hh \
	ecubridge/include/AIMErrorFlagTransform.hh \
	ecubridge/include/AIMExhTempTransform.hh \
	ecubridge/include/AIMFuelPressTransform.hh \
	ecubridge/include/AIMFuelTempTransform.hh \
	ecubridge/include/AIMGearTransform.hh \
	ecubridge/include/AIMLambdaTransform.hh \
	ecubridge/include/AIMManifPressTransform.hh \
	ecubridge/include/AIMOilPressTransform.hh \
	ecubridge/include/AIMOilTempTransform.hh \
	ecubridge/include/AIMRPMTransform.hh \
	ecubridge/include/AIMThrotAngTransform.hh \
	ecubridge/include/AIMWaterTempTransform.hh \
	ecubridge/include/AIMWheelSpeedTransform.hh \
	ecubridge/include/ChannelManager.hh \
	ecubridge/include/DataTransformer.hh \
	ecubridge/include/USBCable.hh \
	ecubridge/include/SoloDLPort.hh \
	ecubridge/include/DataTransformer.hh \
	ecubridge/include/CommandPort.hh \
	ecubridge/include/DL32Chan1Transform.hh \
	ecubridge/include/DL32Chan2Transform.hh \
	ecubridge/include/DL32Chan3Transform.hh \
	ecubridge/include/DL32Chan4Transform.hh \
	ecubridge/include/DL32Chan5Transform.hh \
	ecubridge/include/ManualTransform.hh \
	ecubridge/include/NullTransform.hh \
	ecubridge/include/PassthroughTransform.hh

ECU_OBJ   = \
	obj/ChannelManager.o \
	obj/DL32Port.o \
	obj/SoloDLPort.o \
	obj/CommandPort.o \
	obj/USBCable.o \
	obj/ECUBridge.o
	
# the ecu bridge daemon

all: daemon logger

daemon: obj/ecubridge

obj/ecubridge: obj/libutil.a $(UTIL_HDRS) $(ECU_OBJ) ecubridge/src/ecubridgemain.cc
	@echo "[LD] ecubridge"
	@$(CC) $(CFLAGS) $(LDFLAGS) ecubridge/src/ecubridgemain.cc $(ECU_OBJ) -lutil -ludev -o $@

cmtest: lib $(UTIL_HDRS) $(ECU_OBJ) test/cmtest.cc
	@echo "[LD] cmtest"
	@$(CC) $(CFLAGS) $(LDFLAGS) test/cmtest.cc $(ECU_OBJ) -lutil -o test/$@

dl32test: lib $(UTIL_HDRS) $(ECU_OBJ) test/dl32test.cc
	@echo "[LD] dl32test"
	@$(CC) $(CFLAGS) $(LDFLAGS) test/dl32test.cc $(ECU_OBJ) -lutil -o test/$@
 
solodltest: lib $(UTIL_HDRS) $(ECU_OBJ) test/solodltest.cc
	@echo "[LD] solodltest"
	@$(CC) $(CFLAGS) $(LDFLAGS) test/solodltest.cc $(ECU_OBJ) -lutil -o test/$@
	
cmdtest: lib $(UTIL_HDRS) $(ECU_OBJ) test/cmdtest.cc
	@echo "[LD] cmdtest"
	@$(CC) $(CFLAGS) $(LDFLAGS) test/cmdtest.cc $(ECU_OBJ) -lutil -o test/$@
	
usbtest: lib $(UTIL_HDRS) $(ECU_OBJ) test/usbtest.cc
	@echo "[LD] usbtest"
	@$(CC) $(CFLAGS) $(LDFLAGS) test/usbtest.cc $(ECU_OBJ) -lutil -ludev -o test/$@
	
# ecu data logger rules

logger: obj/ecudatalogger

obj/ecudatalogger: obj/libutil.a $(UTIL_HDRS) $(LOGGER_OBJ) ecudatalogger/src/ecudatalogger.cc
	@echo "[LD] ecudatalogger"
	@$(CC) $(CFLAGS) $(LDFLAGS) ecudatalogger/src/ecudatalogger.cc $(LOGGER_OBJ) -lutil -o $@

obj/RRDConnector.o: $(LOGGER_HDRS) ecudatalogger/src/RRDConnector.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecudatalogger/src,$(patsubst %.o,%.cc,$@)) -o $@
	
# ecu daemon rules

obj/ECUBridge.o: $(ECU_HDRS) ecubridge/src/ECUBridge.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@
	
obj/ChannelManager.o: $(ECU_HDRS) ecubridge/src/ChannelManager.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@
	
obj/DL32Port.o: $(ECU_HDRS) ecubridge/src/DL32Port.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@
	
obj/SoloDLPort.o: $(ECU_HDRS) ecubridge/src/SoloDLPort.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@
	
obj/CommandPort.o: $(ECU_HDRS) ecubridge/src/CommandPort.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/USBCable.o: $(ECU_HDRS) ecubridge/src/USBCable.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,ecubridge/src,$(patsubst %.o,%.cc,$@)) -o $@

# util library rules 

obj/util.o: $(UTIL_HDRS) util/src/util.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/IniFile.o: $(UTIL_HDRS) util/src/IniFile.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/ConfigManager.o: $(UTIL_HDRS) util/src/ConfigManager.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/LogManager.o: $(UTIL_HDRS) util/src/LogManager.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/RS232Port.o: $(UTIL_HDRS) util/src/RS232Port.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/PortMapper.o: $(UTIL_HDRS) util/src/PortMapper.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/DataTapWriter.o: $(UTIL_HDRS) util/src/DataTapWriter.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/DataTapReader.o: $(UTIL_HDRS) util/src/DataTapReader.cc
	@echo "[CC] $@" 
	@$(CC) -c $(CFLAGS) $(subst obj,util/src,$(patsubst %.o,%.cc,$@)) -o $@

obj/libutil.a: obj/util.o obj/IniFile.o obj/ConfigManager.o \
	obj/LogManager.o obj/RS232Port.o obj/PortMapper.o obj/DataTapWriter.o \
	obj/DataTapWriter.o obj/DataTapReader.o
	@echo "[AR] $@"
	@$(AR) $(ARFLAGS) $@ $? 2>&1

lib: obj/libutil.a


# test programs 	

rtaptest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc util/include/Object.hh \
	util/include/DataTapReader.hh util/src/DataTapReader.cc \
	test/rtaptest.cc 
	@echo "[LD] rtaptest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc \
	util/src/LogManager.cc util/src/DataTapReader.cc  test/rtaptest.cc -o test/$@
	
wtaptest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc util/include/Object.hh \
	util/include/DataTapWriter.hh util/src/DataTapWriter.cc \
	test/wtaptest.cc 
	@echo "[LD] wtaptest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc \
	util/src/LogManager.cc util/src/DataTapWriter.cc  test/wtaptest.cc -o test/$@

maptest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc util/include/Object.hh \
	util/include/PortMapper.hh util/src/PortMapper.cc \
	test/logtest.cc 
	@echo "[LD] maptest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc \
	util/src/LogManager.cc util/src/PortMapper.cc  test/maptest.cc -o test/$@
	
porttest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc util/include/Object.hh \
	util/include/RS232Port.hh util/src/RS232Port.cc \
	test/logtest.cc 
	@echo "[LD] porttest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc \
	util/src/LogManager.cc util/src/RS232Port.cc  test/porttest.cc -o test/$@

rrdtest: $(UTIL_HDRS) $(LOGGER_HDRS) lib \
	ecudatalogger/src/RRDConnector.cc test/rrdtest.cc 
	@echo "[LD] rrdtest"
	@$(CC) $(CFLAGS) $(LDFLAGS) ecudatalogger/src/RRDConnector.cc test/rrdtest.cc -o test/$@ -lutil
	
objtest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc util/include/Object.hh \
	test/logtest.cc 
	@echo "[LD] objtest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc util/src/LogManager.cc test/objtest.cc -o test/$@

logtest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
	util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
	util/include/LogManager.hh util/src/LogManager.cc \
	test/logtest.cc 
	@echo "[LD] logtest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc util/src/LogManager.cc test/logtest.cc -o test/$@

initest: util/include/util.hh util/src/util.cc util/include/IniFile.hh \
  util/src/IniFile.cc util/include/ConfigManager.hh util/src/ConfigManager.cc \
  test/initest.cc 
	@echo "[LD] initest"
	@$(CC) $(CFLAGS) util/src/util.cc util/src/IniFile.cc util/src/ConfigManager.cc test/initest.cc -o test/$@

utiltest: util/include/util.hh util/src/util.cc test/utiltest.cc
	@echo "[LD] utiltest"
	@$(CC) $(CFLAGS) util/src/util.cc test/utiltest.cc -o test/$@

# install

install: logger daemon
	cp obj/ecubridge /usr/local/bin/ecubridge
	chmod a+rx /usr/local/bin/ecubridge
	cp obj/ecudatalogger /usr/local/bin/ecudatalogger
	chmod a+rx /usr/local/bin/ecudatalogger

# clean!

clean:
	rm -f test/initest test/logtest test/maptest test/objtest \
	test/porttest test/readtest test/rtaptest test/utiltest \
	test/wtaptest
	rm -f obj/*.o
	rm -f obj/libutil.a
	rm -f obj/ecubridge

	