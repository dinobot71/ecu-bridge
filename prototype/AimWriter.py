import serial
import copy
import time
#Open Write port as 19200,8,N,1
#AimSerialPort = serial.Serial('COM3',19200)
#print("Aim Serial Port as: " + AimSerialPort.name)


#Packet relative Frequencies by and channel numbers
#Channel Map = [RPM,WheelSPD,OILP,COOLNT,FUELP,BATT,TPS,MP,IAT,EGT,LAMBDA,FUELTMP,GEAR,ERR]
#assumes values are already converted to AIM format - see reference documentation from AIM

channelNumber = [1,5,9,13,17,21,33,45,69,97,101,105,109,113,125]
channelFrequency = [10,10,5,2,2,5,5,10,10,2,2,10,2,5,2]
channelFactor = [10/f for f in channelFrequency]
numChannels = len(channelNumber)
channelData = [0] * numChannels
maxFrequency = max(channelFrequency)



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
    print(packet)
    #AimSerialPort.write(packet)


while(True):
    #Old Way
    """
    startSend = time.time()
    #Main Loop body
    packetsRemaining = copy.deepcopy(channelFrequency)
    for j in range(maxFrequency):
        for i in range(numChannels):
            if packetsRemaining[i] > 0: #If there's a still an update remaining in this second
                packetsRemaining[i] -= 1 #one less packet to send
                print(channelNumber[i])
                writePacket(channelNumber[i],channelData[i])
    elapsedTime = time.time() - startSend
    waitTime = 1 - elapsedTime
    if waitTime < 0:
        waitTime = 0
    time.sleep(waitTime)
    """
    #print(channelFactor)
    for slot in range(1,11): #10 slots/second
        startSend = time.time()
        #send the packets appropriate multiples of 10
        for i in range(len(channelFactor)):
            if slot % channelFactor[i] == 0:
                writePacket(channelNumber[i],channelData[i])
        elapsedTime = time.time() - startSend
        waitTime = 0.1 - elapsedTime
        if waitTime < 0:
            waitTime = 0
        time.sleep(waitTime)        
        
        
    

"""
fakestream1 = bytes(b'\xA2\x83\x00\x7A\x01\x7F\x01\x00')#3 Word fake stream, all 
actualHeader = bytes(b'\xA2\x80')
header = fakestream1[0:2]
if (actualHeader[0] & header[0] ==  actualHeader[0]) and (actualHeader[1] & header[1] == actualHeader[1]):
    print("Header Found")
    length = (header[0] & 0x01)*128 + (header[1] & 0x7F) #read number of data words following header
    print(length)
    #payload = InnovateSerialPort.read(length*2) #read the data (bytes = 2*words)
    payload = fakestream1[2:]
    for i in range(0, len(payload),2): #iterate through data stream 1 word at a time (2 bytes)
        word = payload[i]*256 + payload[i+1]
        #print(word)
        #confirm it's a DL-32 sub-packet
        if (payload[i] & 0xC0) == 0 and (payload[i+1] & 0x80) == 0:
            word = payload[i]*256 + payload[i+1]
            print(word)
"""   
        
#AimSerialPort.close()
