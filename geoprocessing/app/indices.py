import numpy as np
from numpy.typing import ArrayLike, NDArray


def normalized_difference(first: ArrayLike, second: ArrayLike) -> NDArray[np.float64]:
    """Calcula a diferença normalizada; denominador zero resulta em NaN."""
    a = np.asarray(first, dtype=np.float64)
    b = np.asarray(second, dtype=np.float64)
    if a.shape != b.shape:
        raise ValueError("As bandas devem ter a mesma dimensão.")
    denominator = a + b
    result = np.full(a.shape, np.nan, dtype=np.float64)
    np.divide(a - b, denominator, out=result, where=denominator != 0)
    return result


def ndvi(nir: ArrayLike, red: ArrayLike) -> NDArray[np.float64]:
    return normalized_difference(nir, red)


def ndwi(green: ArrayLike, nir: ArrayLike) -> NDArray[np.float64]:
    return normalized_difference(green, nir)


def nbr(nir: ArrayLike, swir: ArrayLike) -> NDArray[np.float64]:
    return normalized_difference(nir, swir)
