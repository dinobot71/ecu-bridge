#ifndef MANUALTRANSFORM_HH
#define MANUALTRANSFORM_HH

#include "DataTransformer.hh"

class ManualTransform : public DataTransformer {

  private:

  protected:

  public:

    /* standard constructor */

    ManualTransform(void) {
      setName("Manual");
    }

    /**
     *
     * y() - transform 'x' by whatever transformation
     * function we implement.
     *
     */

    virtual unsigned int y(unsigned int x) {
      return parameters[0];
    }

    /**
     *
     * inverse() - given an input 'x', product the inverse
     * of what y() would normally do...to cancel out y().
     * If you then do y() on the output of this function,
     * you should get 'x' again.
     *
     */

    virtual unsigned int inverse(unsigned int x) {
      return parameters[0];
    }

    /* standard destructor */

    virtual ~ManualTransform() {

    }
};


#endif
