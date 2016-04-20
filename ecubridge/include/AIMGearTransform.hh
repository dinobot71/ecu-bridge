#ifndef AIMGEARTRANSFORM_HH
#define AIMGEARTRANSFORM_HH

#include "DataTransformer.hh"

class AIMGearTransform : public DataTransformer {

  private:

  protected:

  public:

    /* standard constructor */

    AIMGearTransform(void) {
      setName("Gear");
    }

    /**
     *
     * y() - transform 'x' by whatever transformation
     * function we implement.
     *
     */

    virtual unsigned int y(unsigned int x) {

      if(x>3) {
        x = 3;
      }

      return x;
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
     
      if(x>3) {
        x = 3;
      }

      return x;
    }

    /* standard destructor */

    virtual ~AIMGearTransform() {

    }
};


#endif
