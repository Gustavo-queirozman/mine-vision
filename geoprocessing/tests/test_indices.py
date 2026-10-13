import numpy as np
import pytest

from app.indices import nbr, ndvi, ndwi, normalized_difference


def test_normalized_difference_calculates_values_and_marks_zero_denominators():
    result = normalized_difference([3, 1, 0], [1, -1, 0])

    np.testing.assert_allclose(result[:1], [0.5])
    assert np.isnan(result[1])
    assert np.isnan(result[2])


@pytest.mark.parametrize(
    ("function", "first", "second", "expected"),
    [
        (ndvi, [0.8], [0.2], [0.6]),
        (ndwi, [0.6], [0.2], [0.5]),
        (nbr, [0.7], [0.3], [0.4]),
    ],
)
def test_spectral_index_helpers_delegate_to_normalized_difference(function, first, second, expected):
    np.testing.assert_allclose(function(first, second), expected)


def test_normalized_difference_rejects_bands_with_different_shapes():
    with pytest.raises(ValueError, match="mesma dimensão"):
        normalized_difference([1, 2], [1])
