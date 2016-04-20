import serial
#Open Read port as 19200,8,N,1
InnovateSerialPort = serial.Serial('COM3',19200)
print("Innovate Serial Port as: " + InnovateSerialPort.name)
actualHeader = bytes(b'\xA2\x80')
while(True):
    if InnovateSerialPort.in_waiting >= 2: #If there's data waiting, need at least 2 bytes for a packetheader
        header = InnovateSerialPort.read(2) #Read the header packet
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
        
InnovateSerialPort.close()
