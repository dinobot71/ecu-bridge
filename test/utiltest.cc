#include "util.hh"

int main(int argc, const char* argv[]) {

  string path;

  cout << "Util unit tests..." << endl;

  {
    cout << "[executable_path] ..." << endl;

    if(!executable_path(path, argv[0])) {
      cout << "[FAIL] can't find path of executable." << endl;
      return 1;
    }

    if(path.empty()) {
      cout << "[FAIL] no path found." << endl;
      return 1;
    }

    cout << "[OK] path: " << path << endl;
  }

  {
    cout << "[getcwd] ..." << endl;

    if(!getcwd(path)) {
      cout << "[FAIL] can't find working directory." << endl;
      return 1;
    }

    if(path.empty()) {
      cout << "[FAIL] no working directory" << endl;
      return 1;
    }

    cout << "[OK] cwd: " << path << endl;
  }

  {
    cout << "[dirname] ..." << endl;

    string dir;
    if(!dirname("/etc/passwd", dir)) {
      cout << "[FAIL] Can't do dirname()" << endl;
      return 1;
    }

    if(path.empty()) {
      cout << "[FAIL] no directory" << endl;
      return 1;
    }

    if(dir != "/etc") {
      cout << "[FAIL] wrong directory: " << dir << " != /etc" << endl;
    }

    cout << "[OK] dirname(): " << dir << endl;
  }

  {
    cout << "[filename] ..." << endl;

    string file;
    if(!filename("/etc/passwd", file)) {
      cout << "[FAIL] Can't do filename()" << endl;
      return 1;
    }

    if(path.empty()) {
      cout << "[FAIL] no file" << endl;
      return 1;
    }

    if(file != "passwd") {
      cout << "[FAIL] wrong directory: " << file << " != passwd" << endl;
    }

    cout << "[OK] filename(): " << file << endl;
  }

  {
    cout << "[file_exists] ..." << endl;

    if(!file_exists("/etc/passwd")) {
      cout << "[FAIL] /etc/passwd should exist." << endl;
      return 1;
    }

    if(file_exists("/etc/passwd.garvin")) {
      cout << "[FAIL] /etc/passwd.garvin should not exist." << endl;
      return 1;
    }

    cout << "[OK] file_exists()" << endl;
  }

  {
    cout << "[file] ..." << endl;
    string path;
    if(!executable_path(path, argv[0])) {
      cout << "[FAIL] Can't get executable path." << endl;
      return 1;
    }

    string dir;
    if(!dirname(path, dir)) {
      cout << "[FAIL] Can't get dirname." << endl;
      return 1;
    }

    path = dir + "/readtest";

    if(!file_exists(path)) {
      cout << "[FAIL] Can't find read test file: " << path << endl;
      return 1;
    }

    vector<string> lines;
    if(!file(path, lines)) {
      cout << "[FAIL] can not read contents: ./readtest" << endl;
      return 1;
    }

    if(lines.empty()) {
      cout << "[FAIL] expecting some lines." << endl;
      return 1;
    }

    for(int i=0; i<lines.size(); i++) {
      cout << ". " << lines[i] << endl;
    }

    if(lines.size() < 5) {
      cout << "[FAIL] expecting at least 5 lines." << endl;
      return 1;
    }

    cout << "[OK] file()" << endl;
  }

  {
    cout << "[trim] ..." << endl;

    string str = " too much    space   ";

    str = trim(str);

    if(str != "too much    space") {
      cout << "[FAIL] not a complete trim: " << str << endl;
      return 1;
    }

    cout << "[OK] trim()" << endl;
  }

  {
    cout << "[reduce] ..." << endl;

    string str = " too much    space   ";

    str = reduce(str, "-");

    if(str != "too-much-space") {
      cout << "[FAIL] incorrect reduce: " << str << endl;
      return 1;
    }

    cout << "[OK] reduce()" << endl;
  }

  {
    cout << "[explode] ..." << endl;

    string blah = "___this_ is__ th_e str__ing we__ will use__";
    vector<string> tokens;

    if(!explode(blah, " _", tokens)) {
      cout << "[FAIL] could not explode!" << endl;
      return 1;
    }

    for(int i=0; i<tokens.size(); i++) {
      cout << " . " << tokens[i] << endl;
    }
    cout << "." << endl;

    if(tokens.size() != 9) {
      cout << "[FAIL] expecting 9 tokens but got: " << tokens.size() << endl;
      return 1;
    }

    cout << "[OK] explode()" << endl;
  }

  {
    cout << "[implode] ..." << endl;

    string str;
    vector<string> tokens;
    tokens.push_back("this");
    tokens.push_back("is");
    tokens.push_back("th");
    tokens.push_back("e");
    tokens.push_back("str");

    if(!implode(tokens, "__", str)) {
      cout << "[FAIL] could not implode!" << endl;
      return 1;
    }

    if(str != "this__is__th__e__str") {
      cout << "[FAIL] bad implosion: " << str << endl;
      return 1;
    }

    cout << "[OK] implode()" << endl;
  }

  {
    cout << "[is_numeric] ..." << endl;

    if(!is_numeric("0.")) {
      cout << "[FAIL] 0. should be a number." << endl;
      return 1;
    }
    if(!is_numeric("0.132")) {
      cout << "[FAIL] 0.132 should be a number." << endl;
      return 1;
    }
    if(!is_numeric("100.99")) {
      cout << "[FAIL] 100.99 should be a number." << endl;
      return 1;
    }
    if(!is_numeric("100")) {
      cout << "[FAIL] 100 should be a number." << endl;
      return 1;
    }
    if(!is_numeric("32.7e12")) {
      cout << "[FAIL] 100 should be a number." << endl;
      return 1;
    }

    if(is_numeric("")) {
      cout << "[FAIL] '' should not be a number." << endl;
      return 1;
    }
    if(is_numeric("foop")) {
      cout << "[FAIL] 'foop' should not be a number." << endl;
      return 1;
    }
    if(is_numeric("123 foop")) {
      cout << "[FAIL] '123 foop' should not be a number." << endl;
      return 1;
    }
    if(is_numeric("foop 123")) {
      cout << "[FAIL] 'foop 123' should not be a number." << endl;
      return 1;
    }

    cout << "[OK] is_numeric()" << endl;
  }


  {
    cout << "[exec] ..." << endl;

    int status;
    vector<string> output;

    if(!exec("/usr/bin/whoami", output, status)) {
      cout << "[FAIL] can't invoke command" << endl;
      return 1;
    }

    if(output.size() == 0) {
      cout << "[FAIL] no output from command" << endl;
      return 1;
    }
    string line = trim(output[0]);

    if(line != "chumpcar") {
      cout << "[FAIL] bad output: " << line << endl;
      return 1;
    }

    cout << "[OK] exec()" << endl;
  }

  {
    cout << "[whoami] ..." << endl;

    string userid;

    if(!whoami(userid)) {
      cout << "[FAIL] can't get userid." << endl;
      return 1;
    }

    if(userid.empty()) {
      cout << "[FAIL] no userid." << endl;
      return 1;
    }

    if(userid != "chumpcar") {
      cout << "[FAIL] bad userid: " << userid << endl;
      return 1;
    }

    cout << "[OK] whoami()" << endl;
  }

  cout << "Util unit testing done." << endl;

  return 0;
}
