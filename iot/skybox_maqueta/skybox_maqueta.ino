#include <SPI.h>
#include <MFRC522.h>

MFRC522 rfid(10, 9); // SDA=10, RST=9

const int N = 6;
const int PINES[N] = {2, 3, 4, 5, 6, 7};
const char* NOMBRES[N] = {"D2", "D3", "D4", "D5", "D6", "D7"};
const char* PUESTOS[N] = {"P01-01", "P01-02", "P01-03", "P01-04", "P01-05", "P01-06"};
int estado[N] = {-1, -1, -1, -1, -1, -1};

String ultimoUid = "";
unsigned long ultimoRfidMs = 0;
unsigned long ultimoCheckRfidMs = 0;

void setup() {
  Serial.begin(115200);
  SPI.begin();
  rfid.PCD_Init();
  delay(20);

  byte ver = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  Serial.print("RC522_VER:0x");
  Serial.println(ver, HEX);

  for (int i = 0; i < N; i++) {
    pinMode(PINES[i], INPUT_PULLUP);
    delay(5);
    estado[i] = (digitalRead(PINES[i]) == LOW) ? 1 : 0;
  }
  Serial.println("READY");

  for (int i = 0; i < N; i++) {
    Serial.print("SENSOR:");
    Serial.print(PUESTOS[i]);
    Serial.print(":");
    Serial.println(estado[i]);
  }
}

void loop() {
  for (int i = 0; i < N; i++) {
    int lectura = digitalRead(PINES[i]);
    int ocupado = (lectura == LOW) ? 1 : 0;
    if (ocupado != estado[i]) {
      delay(80);
      if (((digitalRead(PINES[i]) == LOW) ? 1 : 0) == ocupado) {
        estado[i] = ocupado;
        Serial.print("SENSOR:");
        Serial.print(PUESTOS[i]);
        Serial.print(":");
        Serial.println(ocupado);
      }
    }
  }

  // Auto-recuperacion del lector RC522 si un cable de protoboard se mueve
  if (millis() - ultimoCheckRfidMs > 1500) {
    ultimoCheckRfidMs = millis();
    if ((rfid.PCD_ReadRegister(MFRC522::TxControlReg) & 0x03) != 0x03) {
      rfid.PCD_Init();
    }
  }

  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    String uid = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
      if (rfid.uid.uidByte[i] < 0x10) uid += "0";
      uid += String(rfid.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();

    if (uid != ultimoUid || (millis() - ultimoRfidMs) > 2500) {
      ultimoUid = uid;
      ultimoRfidMs = millis();
      Serial.print("RFID:");
      Serial.println(uid);
    }
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
    rfid.PCD_Init();
  }
}
