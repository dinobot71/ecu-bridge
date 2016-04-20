import serial
import copy
import time
import threading
#Open Write port as 19200,8,N,1
AimSerialPort = serial.Serial('COM3',19200)
print("Aim Serial Port as: " + AimSerialPort.name)
#Open Read port as 19200,8,N,1
InnovateSerialPort = serial.Serial('COM3',19200)
print("Innovate Serial Port as: " + InnovateSerialPort.name)
actualHeader = bytes(b'\xA2\x80')


#Packet relative Frequencies by and channel numbers
#Channel Map = [RPM,WheelSPD,OILP,COOLNT,FUELP,BATT,TPS,MP,IAT,EGT,LAMBDA,FUELTMP,GEAR,ERR]
#assumes values are already converted to AIM format - see reference documentation from AIM

channelNumber = [1,5,9,13,17,21,33,45,69,97,101,105,109,113,125]
channelFrequency = [10,10,5,2,2,5,5,10,10,2,2,10,2,5,2]
channelFactor = [10/f for f in channelFrequency]
numChannels = len(channelNumber)
channelData = [0] * numChannels
maxFrequency = max(channelFrequency)

def inputThread():
    while(true):
        header = InnovateSerialPort.read(2) #BLOCKING READ: Read the header packet
        #Header should by bytes object, split and verify it's the header
        if (actualHeader[0] & header[0] ==  actualHeader[0]) and (actualHeader[1] & header[1] == actualHeader[1]):
            print("Header Found")
            length = (header[0] & 0x01)*128 + (header[1] & 0x7F) #read number of data words following header
            print(length)
            payload = InnovateSerialPort.read(length*2) #read the data (bytes = 2*words)
            for i in range(0, len(payload),2): #iterate through data stream 1 word at a time (2 bytes)
                word = payload[i]*256 + payload[i+1]
                #print(word)
                #confirm it's a DL-32 sub-packet
                if (payload[i] & 0xC0) == 0 and (payload[i+1] & 0x80) == 0:
                    word = payload[i]*256 + payload[i+1]
                    print(word)

def writePacket(channel,rawData):
    packet = bytearray()
    packet.append(channel)
    packet.append(0xA3)
    packet.append(rawData >> 8)
    packet.append(rawData % 256)
    #print(packet)
    checksum = 0
    for i in packet:
        checksum += i
    #print(checksum%256)
    packet.append(checksum%256)
    #print(packet)
    AimSerialPort.write(packet)


def outputThread():
    while(true):
        #print(channelFactor)
        for slot in range(1,11): #10 slots/second
            startSend = time.time()
            #send the packets appropriate multiples of 10
            for i in range(len(channelFactor)):
                if slot % channelFactor[i] == 0:
                    writePacket(channelNumber[i],channelData[i])
            elapsedTime = time.time() - startSend
            waitTime = 0.1 - elapsedTime
            if waitTime > 0:
                time.sleep(waitTime)        
     AimSerialPort.close()       

readerThread = threading.Thread(target=inputThread)
readerThread.daemon = True
readerThread.start()
writerThread = threading.Thread(target=outputThread)
writerThread.daemon = True
writerThread.start()
while(True)
    pass
        

