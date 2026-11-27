<?php

it('pluralises Czech nouns', function () {
    expect(cz_plural(1, 'odpověď', 'odpovědi', 'odpovědí'))->toBe('1 odpověď')
        ->and(cz_plural(3, 'odpověď', 'odpovědi', 'odpovědí'))->toBe('3 odpovědi')
        ->and(cz_plural(5, 'odpověď', 'odpovědi', 'odpovědí'))->toBe('5 odpovědí')
        ->and(cz_plural(0, 'odpověď', 'odpovědi', 'odpovědí'))->toBe('0 odpovědí');
});

it('formats numbers the Czech way', function () {
    expect(cz_number(2418))->toBe("2\u{00A0}418")
        ->and(cz_number(0.5, 2))->toBe('0,50');
});
