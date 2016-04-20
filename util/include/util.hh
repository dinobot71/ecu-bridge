#ifndef UTIL_HH
#define UTIL_HH

#include <stdio.h>
#include <errno.h>
#include <stdlib.h>
#include <termios.h>
#include <unistd.h>
#include <fcntl.h>
#include <time.h>
#include <sys/ioctl.h>
#include <sys/select.h>
#include <sys/time.h>
#include <sys/types.h>

#include <array>
#include <vector>
#include <string>
#include <regex>
#include <iostream>

using namespace std;

/*
 * common constants
 *
 */

enum class Device {
  DL32,
  SOLODL
};

/*
 * helper functions
 *
 */


/**
 *
 * my_ip() - fetch my own IP address.
 *
 * @param ip string - the returned IP address
 *
 * @return bool - exactly false if we cna't find it.
 *
 */

bool my_ip(string & ip);

/**
 *
 * whoami() - fetch the current userid.
 *
 * @param userid string - the userid we pass back
 *
 * @return bool - exactly false on error.
 *
 */

bool whoami(string & userid);

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

bool exec(const string & command, vector<string> & output, int & status);

/**
 *
 * is_numeric() - check if the given string looks like a number.
 *
 * @param str string - the string to check
 *
 * @return bool return exactly true if its a number.
 *
 */

bool is_numeric(const string & str);

/**
 *
 * tolower() - generate a lower case version of a string
 *
 * Taken from:
 *
 * http://stackoverflow.com/questions/313970/how-to-convert-stdstring-to-lower-case
 *
 */

string strtolower(const string & str);

/**
 *
 * tolower() - generate a lower case version of a string
 *
 * Taken from:
 *
 * http://stackoverflow.com/questions/313970/how-to-convert-stdstring-to-lower-case
 *
 */

string strtoupper(const string & str);

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

extern bool implode(const vector<string> & tokens, const string & glue, string & str);

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

extern bool explode(const string & str, const string & delim, vector<string> & tokens);

/**
 *
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

extern string trim(const string & str,
                   const string & whitespace = " \t\r\n");

/**
 *
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

extern string reduce(const string & str,
                     const string & fill = " ",
                     const string & whitespace = " \t\r\n");

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

extern bool dirname(const string & path, string & directory);

/**
 *
 * filename() given a string which is presumably a path,
 * return the file portion.
 *
 * @param path string the input path
 *
 * @param file string the file portion
 *
 * @return bool return exactly false if there is an error.
 *
 */

extern bool filename(const string & path, string & file);

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

bool executable_path(string & path, const string & argv0 = "");

/**
 *
 * getcwd() fetch the current working directory.
 *
 * @param path string the result (on success)
 *
 * @return bool return exactly false on error.
 *
 */

extern bool getcwd(string & path);

/**
 *
 * file_exists() given a file or directory name, check to
 * see if it actually exists.
 *
 * @param fileName string the file or directory name to check.
 *
 * @return bool, return exactly false if it doesn't exist.
 *
 */

extern bool file_exists(string fileName);

/**
 *
 * file() given a file name, read into an array of
 * strings that we can work with easily in memory.
 *
 * @param fileName string the name of the file to read
 * @param results the lines of the file
 *
 * @return bool return exactly false if there is
 * an error.
 *
 */

extern bool file(string fileName, vector<string> & results);

#endif
