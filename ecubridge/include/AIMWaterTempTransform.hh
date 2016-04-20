#ifndef AIMWATERTEMPTRANSFORM_HH
#define AIMWATERTEMPTRANSFORM_HH

#include "DataTransformer.hh"

class AIMWaterTempTransform : public DataTransformer {

  private:

  protected:

  public:

    /* standard constructor */

    AIMWaterTempTransform(void) {
      setName("Water Temp");
    }

    /**
     *
     * y() - transform 'x' by whatever transformation
     * function we implement.
     *
     */

    virtual unsigned int y(unsigned int x) {

      double yy = ((double)x)/10.0 - 100;

      return (unsigned int)yy;
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

      double yy = ((double)x + 100.0) * 10.0;

      return yy;
    }

    /* standard destructor */

    virtual ~AIMWaterTempTransform() {

    }
};


#endif
