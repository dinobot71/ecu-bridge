#ifndef OBJECT_HH
#define OBJECT_HH

#include "LogManager.hh"

class Object {

  private:

    /**
     *
     * lastError - the most recent error reported by this
     * object.
     *
     */

    string lastError;

    /**
     *
     * ready - exactly true if this object is ready for use.
     *
     */

    bool ready;

  protected:

    /**
     *
     * className - the name of the most concrete sub-class.
     *
     */

    string className;

    void setClassName(const string & name) {
      className = name;
    }

    const string & getClassName(void) {
      return className;
    }

  public:

    /* standard constructor */

    Object(const string & myName="Object") :
      className(myName), lastError(""), ready(true) {

      /* nothing yet */

    }

    Object(const Object & obj) {
      operator=(obj);
    }

    Object &operator=(const Object & obj) {

      className = "Object";
      ready     = obj.ready;
      lastError = obj.lastError;

      return *this;
    }

    /**
     *
     * unReady() - force to unusable state
     *
     */

    void unReady(void) {
      ready = false;
    }

    /**
     *
     * makeReady() - force to usable state
     *
     */

    void makeReady(void) {
      ready = true;
    }

    /**
     *
     * isReady() - test if object is ready for use.
     *
     */

    bool isReady(void) {
      return ready;
    }

    /**
     *
     * getError() - fetch the most recent error message.
     *
     */

    string getError(void) {
      return lastError;
    }

    /*
     * basic logging support methods, we're dumbing it
     * down so you just have to pass in a string.
     *
     */

    void fatal(const string & msg) {
      LogManager::fatal("[%v] %v", className, msg);
    }

    void error(const string & msg) {
      LogManager::error("[%v] %v", className, msg);
    }

    void warning(const string & msg) {
      LogManager::warning("[%v] %v", className, msg);
    }

    void info(const string & msg) {
      LogManager::info("[%v] %v", className, msg);
    }

    void debug(const string & msg) {
      LogManager::debug("[%v] %v", className, msg);
    }

    void trace(const string & msg) {
      LogManager::trace("[%v] %v", className, msg);
    }

    /**
     *
     * setError() - set the error message.
     *
     */

    void setError(const string & msg) {
      lastError = msg;
      error(lastError);
    }

    /* standard destructor */

    virtual ~Object(void) {

    }

};


#endif
