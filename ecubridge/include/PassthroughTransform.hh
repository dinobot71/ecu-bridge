#ifndef PASSTHROUGHTRANSFORM_HH
#define PASSTHROUGHTRANSFORM_HH

#include "DataTransformer.hh"

class PassthroughTransform : public DataTransformer {

  private:

  protected:

  public:

    /* standard constructor */

    PassthroughTransform(void) {
      setName("Passthrough");
    }

    /**
     *
     * y() - transform 'x' by whatever transformation
     * function we implement.
     *
     */

    virtual unsigned int y(unsigned int x) {
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
      return x;
    }

    /* standard destructor */

    virtual ~PassthroughTransform() {

    }
};


#endif
