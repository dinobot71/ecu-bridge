#ifndef DATATRANSFORMER_HH
#define DATATRANSFORMER_HH

enum class Transform {
  Null              = 1,
  Passthrough       = 2,
  Manual            = 3,
  AIMRPM            = 4,
  AIMWheelSpeed     = 5,
  AIMOilPress       = 6,
  AIMOilTemp        = 7,
  AIMWaterTemp      = 8,
  AIMFuelPress      = 9,
  AIMBattVolt       = 10,
  AIMThrotAng       = 11,
  AIMManifPress     = 12,
  AIMAirChargeTemp  = 13,
  AIMExhTemp        = 14,
  AIMLambda         = 15,
  AIMFuelTemp       = 16,
  AIMGear           = 17,
  AIMErrorFlag      = 18,
  DL32Chan1         = 19,
  DL32Chan2         = 20,
  DL32Chan3         = 21,
  DL32Chan4         = 22,
  DL32Chan5         = 23
};

/**
 *
 * DataTransformer classes are used to transform input
 * data from the DL-32 to normal data (i.e. if you expect
 * 1 you get 1), or from normal data to SoloDL compliant
 * data.  In addition we allow for a special "manual"
 * transformer which just returns whatever value you set
 * regardless of the input value.
 *
 */

class DataTransformer {

  private:

  protected:

    /**
     *
     * allow transformers to have parameters
     * so that we can have settings that tune
     * the transformation functions.  In the
     * case of a manual transformer, paramA
     * would be the manual value.
     *
     */

    unsigned int parameters[5];

    /**
     *
     * name - name of the transformer (human readable)
     *
     */

    string name;

  public:

    /* standard constructor */

    DataTransformer(void) : name("unknown") {

      for(int i=0; i<5; i++) {
        parameters[i] = 0;
      }
    }

    /**
     *
     * getName() - fetch the name of the transformer
     *
     */

    const string & getName(void) {
      return name;
    }

    /**
     *
     * setName() - set the name of the transformer
     *
     */

    void setName(const string & newName) {
      name = newName;
    }

    /**
     *
     * y() - transform 'x' by whatever transformation
     * function we implement.
     *
     */

    virtual unsigned int y(unsigned int x) {return 0;}

    /**
     *
     * inverse() - given an input 'x', product the inverse
     * of what y() would normally do...to cancel out y().
     * If you then do y() on the output of this function,
     * you should get 'x' again.
     *
     */

    virtual unsigned int inverse(unsigned int x) {
      return 0;
    }

    /**
     *
     * setParam() - given a parameter (by index)
     * set it for this transformer.
     *
     * @param index int - the parameter to set
     *
     * @param value unsigned int - the new parameter value
     *
     */

    void setParam(int index, unsigned int value) {

      if((index<0)||(index>4)) {
        return ;
      }

      parameters[index] = value;

    }

    /**
     *
     * getParam() - fetch the current value for
     * the given parameter.
     *
     * @param index int - the parameter to fetch.
     *
     * @return int - the current value.
     *
     */

    unsigned int getParam(int index) {

      if((index<0)||(index>4)) {
        return 0;
      }

      return parameters[index];
    }

    /* standard destructor */

    virtual ~DataTransformer() {

    }
};

#endif
