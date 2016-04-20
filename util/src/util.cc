#include "util/include/util.hh"
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <string.h>
#include <strings.h>
#include <limits.h>
#include <libgen.h>
#include <fstream>
#include <algorithm>
#include <locale>
#include <ifaddrs.h>
#include <netinet/in.h>
#include <netinet/ip.h>
#include <arpa/inet.h>

/**
 *
 * my_ip() - fetch my own IP address.
 *
 * @param ip string - the returned IP address
 *
 * @return bool - exactly false if we cna't find it.
 *
 */

bool my_ip(string & ip) {

  ip = "";

  vector<string> hits;

  struct ifaddrs *ifaddr, *ifa;

  if(getifaddrs(&ifaddr)!=0) {
    return false;
  }

  /*
   * walk through known interfaces, looking for our own IP4 address,
   * skip loopback (127.0.0.1).
   *
   */

  for(ifa=ifaddr; ifa != NULL; ifa=ifa->ifa_next) {

    if (ifa->ifa_addr == NULL) {
      continue;
    }

    int family = ifa->ifa_addr->sa_family;

    if(family != AF_INET) {
      continue;
    }

    struct sockaddr_in *pAddr = (struct sockaddr_in *)ifa->ifa_addr;
    string ipAddr             = inet_ntoa(pAddr->sin_addr);

    hits.push_back(ipAddr);
  }

  freeifaddrs(ifaddr);

  if(hits.empty()) {
    return false;
  }

  /* if there is only 1, return it */

  if(hits.size() == 1) {

    ip = hits[0];

  } else {

    /*
     * if there is more than one, return the first one
     * that is not loopback
     *
     */

    for(auto const & match : hits) {

      if(match != "127.0.0.1") {
        ip = match;
        break;
      }
    }
  }

  if(ip.empty()) {

    /* can't find anything */

    return false;
  }

  return true;
}

/**
 *
 * whoami() - fetch the current userid.
 *
 * @param userid string - the userid we pass back
 *
 * @return bool - exactly false on error.
 *
 */

bool whoami(string & userid) {

  const char *ptr = cuserid(NULL);
  userid          = "";

  if(ptr == NULL) {
    return false;
  }

  userid = ptr;

  /* all done */

  return true;
}

/**
 *
 * exec() - invoke the given external command and pass the output (lines)
 * back through output, and the process exit code through status.
 *
 * @param command string - the command to run.
 *
 * @param otuput vector - the lines of output
 *
 * @param status int - proess exit status
 *
 * @return bool - exactly false on error.
 *
 */

bool exec(const string & command, vector<string> & output, int & status) {

  if(command.empty()) {

    /* nothing to do */

    return true;
  }

  output.clear();
  status = -1;

  /* start the command ... */

  FILE *fp = NULL;

  if ((fp = popen(command.c_str(), "r")) == NULL) {

    cerr << "ERROR: could not run command: " << command << endl;
    return false;
  }

  /* read the output */

  bool eof = false;

  while(!eof) {

    /* try to read a line */

    int    bufSize = 4096;
    string line    = "";
    bool eol       = false;

    char   buffer[bufSize];
    buffer[0] = '\0';

    while(!eol) {

      if(fgets(buffer, bufSize, fp) == NULL) {
        eof = true;
        break;
      }

      char *ptr = strchr(buffer, '\n');

      if(ptr != NULL) {

        /* we found the end of a line */

        *ptr = '\0';
        eol  = true;
      }

      line.append(buffer);
    }

    output.push_back(line);
  }

  /* if we end with an empty line, chop it off. */

  if(output.size() > 0) {
    if(output[output.size()-1].empty()) {
      output.pop_back();
    }
  }

  /* output has been pulled, try to get the exit status */

  status = pclose(fp);

  if(status < 0) {

    /* could not get exit status */

    cerr << "ERROR: can't get exit status of command: " << command << endl;
    return false;
  }

  /* all done */

  return true;
}

/**
 *
 * is_numeric() - check if the given string looks like a number.
 *
 * @param str string - the string to check
 *
 * @return bool return exactly true if its a number.
 *
 */

bool is_numeric(const string & str) {

  string datum = trim(str);

  /* if its empty, its not a number */

  if(datum.empty()) {
    return false;
  }

  /* if it has internal spaces, its not a number */

  if(datum.find_first_of(" \t\r\n") != string::npos) {
    return false;
  }

  /* ok, try to parse */

  errno        = 0;
  double value = strtod(datum.c_str(), NULL);

  if(errno != 0) {

    /* something went wrong */

    return false;
  }

  /*
   * in the case of 0, we have to check if it means
   * no value was set or if 0 = "0".
   *
   */

  if(value == 0) {

    /* the string better have all 0 or decimal point */

    for(int i=0; i<datum.size(); i++) {

      if((datum[i] != '0') && (datum[i] != '.')) {

        /* its not 0.0 */

        return false;
      }
    }
  }

  /* all done */

  return true;
}

/**
 *
 * strtolower() - generate a lower case version of a string
 *
 * Taken from:
 *
 * http://stackoverflow.com/questions/313970/how-to-convert-stdstring-to-lower-case
 *
 */

string strtolower(const string & str) {

  std::string data = str;
  std::transform(data.begin(), data.end(), data.begin(), ::tolower);

  return data;
}

/**
 *
 * strtolower() - generate a lower case version of a string
 *
 * Taken from:
 *
 * http://stackoverflow.com/questions/313970/how-to-convert-stdstring-to-lower-case
 *
 */

string strtoupper(const string & str) {

  std::string data = str;
  std::transform(data.begin(), data.end(), data.begin(), ::toupper);

  return data;
}

/**
 *
 * implode() given a vector of tokens, join them back together using
 * the givne 'glue' string (the glue).
 *
 * @param tokens vector<string> - the tokens to join (in order)
 * @param glue string - the glue string to use between tokens
 * @param str the results.
 *
 * @return bool return exactly false on error.
 *
 */

bool implode(const vector<string> & tokens, const string & glue, string & str) {

  str = "";

  if(tokens.empty()) {

    /* nothing to do */

    return true;
  }

  for(int i=0; i<tokens.size(); i++) {

    if(tokens[i].empty()) {
      continue;
    }

    str.append(tokens[i]);

    if(i<(tokens.size() - 1)) {
      str.append(glue);
    }
  }

  /* all done */

  return true;
}

/**
 * explode() - given a string and delimiters, split the string into
 * tokens separated by one or more of the given delimiters.  Return
 * the tokens in the provided vector.
 *
 * @param str string the string to explode
 *
 * @param delim string the characters that separate tokens
 *
 * @param tokens vector<string> the tokens!
 *
 * @return bool return exactly false on any error.
 *
 */

bool explode(const string & str, const string & delim, vector<string> & tokens) {

  /*
   * first reduce the string such that all the possible delimiters become
   * just one to look for, and that there is only one of them between tokens.
   *
   */

  string newDelim;
  newDelim.append(1, delim[0]);

  string input = reduce(str, newDelim, delim);

  /* do we have a string? */

  if(str.empty()) {
    return true;
  }

  /* if no delimiter we return str */

  if(delim.empty()) {
    tokens.push_back(str);
    return true;
  }

  /* if no delimiters in the string, we return str */

  if(str.find_first_of(delim) == string::npos) {
    tokens.push_back(str);
    return true;
  }

  /* ok there is at least two tokens, time to split... */

  auto pos = 0;

  while(pos < input.size()) {

    /* start of next token */

    auto start = input.find_first_not_of(newDelim, pos);

    /* end of next token */

    auto end = input.find_first_of(newDelim, start);

    if(end == string::npos) {
      end = input.size();
    }

    string token = input.substr(start, end-start);

    tokens.push_back(token);

    /* move to next token */

    pos = end;
  }

  /* all done */

  return true;
}

/**
 * trim() - given a string trim whitespace from left and right
 * sides.  You can optionally specify what is meant by whtespace
 * characters.  Taken from:
 *
 *   http://stackoverflow.com/questions/1798112/removing-leading-and-trailing-spaces-from-a-string
 *
 * @param str string - the string to trim
 * @param whitespace - what characters are whitespace.
 * @return string - the trimmed string.
 *
 */

string trim(const string & str,
            const string & whitespace) {

  const auto strBegin = str.find_first_not_of(whitespace);

  if (strBegin == string::npos) {
    return "";
  }

  const auto strEnd = str.find_last_not_of(whitespace);
  const auto strRange = strEnd - strBegin + 1;

  return str.substr(strBegin, strRange);
}


/**
 * reduce() - similar to trim() but we replace the space
 * we find with the given filler string.
 *
 *   http://stackoverflow.com/questions/1798112/removing-leading-and-trailing-spaces-from-a-string
 *
 * @param str string - the string to reduce
 * @param fill - what to replace whitesapce with
 * @param whitespace - what characters are whitespace.
 * @return string - the reduced string.
 *
 */

string reduce(const string & str,
              const string & fill,
              const string & whitespace) {

  /* trim whitespace from outside the string */

  auto result = trim(str, whitespace);

  /* replace the ranges of whitespace *inside* the string */

  auto beginSpace = result.find_first_of(whitespace);

  while (beginSpace != string::npos) {

    const auto endSpace = result.find_first_not_of(whitespace, beginSpace);
    const auto range = endSpace - beginSpace;

    result.replace(beginSpace, range, fill);

    const auto newStart = beginSpace + fill.length();
    beginSpace = result.find_first_of(whitespace, newStart);
  }

  return result;
}

/**
 *
 * dirname() given a string which is presumably a path,
 * return the directory portion.
 *
 * @param path string the input path
 *
 * @param directory string the directory portion
 *
 * @return bool return exactly false if there is an error.
 *
 */

bool dirname(const string & path, string & directory) {

  char buf[PATH_MAX];
  char *result = NULL;

  directory = "";
  strcpy(buf, path.c_str());

  result = dirname(buf);

  if(result == NULL) {
    return false;
  }

  directory = result;
  return true;
}

/**
 *
 * basename() given a string which is presumably a path,
 * return the file portion.
 *
 * @param path string the input path
 *
 * @param file string the file portion
 *
 * @return bool return exactly false if there is an error.
 *
 */

bool filename(const string & path, string & file) {

  char buf[PATH_MAX];
  char *result = NULL;

  file = "";
  strcpy(buf, path.c_str());

  result = basename(buf);

  if(result == NULL) {
    return false;
  }

  file = result;
  return true;
}

/**
 *
 * executable_path() find the full path for this executable.
 * Optionally, you can provide argv[0] (from your main() call),
 * to help identify the path, but this function will attempt
 * to find the path without it if possible.
 *
 * @param path string the resulting path
 *
 * @param argv0 string the value of argv[0] (optional) is
 * an additional hint for what the executable path is.
 *
 * @return bool return exactly false if there is an
 * error.
 *
 */

bool executable_path(string & path, const string & argv0) {

  char buf[PATH_MAX];
  char real[PATH_MAX];

  /* first try the modern way, using /proc/... */

  if(readlink("/proc/self/exe", buf, PATH_MAX) > 0) {

    if(realpath(buf, real) == NULL) {
      return false;
    }
    path = real;
    return true;

  } else if(readlink("/proc/curproc/file", buf, PATH_MAX) > 0) {

    if(realpath(buf, real) == NULL) {
      return false;
    }
    path = real;
    return true;

  } else if(readlink("/proc/self/path/a.out", buf, PATH_MAX) > 0) {

    if(realpath(buf, real) == NULL) {
      return false;
    }
    path = real;
    return true;
  }

  /* do we have argv[0] ? */

  if(argv0.empty()) {

    /* nothing we can do */

    return false;
  }

  /* if argv[0] starts with / its an absolute path already */

  if(argv0[0] == '/') {

    /* absolute path */

    path = argv0;

    return true;
  }

  /* use realpath to resolve an relative or symbolic references. */

  if(realpath(argv0.c_str(), real) == NULL) {

    /* can not resolve */

    return false;
  }

  path = real;

  /* all done */

  return true;
}

/**
 *
 * getcwd() fetch the current working directory.
 *
 * @param path string the result (on success)
 *
 * @return bool return exactly false on error.
 *
 */

bool getcwd(string & path) {

  char cwd[PATH_MAX];
  memset(cwd, '\0', PATH_MAX);

  if(getcwd(cwd, sizeof(cwd)) == NULL) {

    /* can't fetch it */

    return false;
  }

  /* pass it back */

  path = cwd;

  return true;
}

/**
 *
 * file_exists() given a file or directory name, check to
 * see if it actually exists.
 *
 * @param fileName string the file or directory name to check.
 *
 * @return boolean, return exactly false if it doesn't exist.
 *
 */

bool file_exists(string fileName) {

  struct stat buf;

  if(fileName.empty()) {
    return false;
  }

  if(stat(fileName.c_str(), &buf) < 0) {
    return false;
  }

  /* it exists! */

  return true;
}

/**
 *
 * file() given a file name, read into an array of
 * strings that we can work with easily in memory.
 *
 * @param fileName string the name of the file to read
 * @param results the lines of the file
 *
 * @return boolean return exactly false if there is
 * an error.
 *
 */

bool file(string fileName, vector<string> & results) {

  /* do we have a file to ready? */

  if(!file_exists(fileName)) {

    /* no such file */

    return false;
  }

  ifstream file(fileName.c_str());

  if(file.is_open()) {

    while(file.good()) {

      string line;

      getline(file, line);

      results.push_back(line);
    }

  } else {

    /* can't open the file */

    return false;
  }

  /* all done */

  return true;
}
