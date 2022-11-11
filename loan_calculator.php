<?php

/**
 * Simple Loan Calculator
 *
 * This is standalone web application. It can calculate loan amortization
 * schedule based on amount of requested loan, annual interest rate, loan
 * fee and duration of the loan in months. Linear and annuity payment
 * methods are supported.
 *
 * This script requires PHP 7.1 (or later) to run.
 *
 * Contents:
 * 1. General purpose library
 * 2. Fixed point arithmetic
 * 3. Classes for currency and money
 * 4. Application, request and responce
 * 5. Templating library
 * 6. Unit tests
 * 7. Templates
 * 8. Runtime configuration
 * 9. Front controller
 *
 * @author      Dmitry Kolesnikov <kolesnikov.dmitry@gmail.com>
 * @copyright   2017 Dmitry Kolesnikov
 * @license     https://creativecommons.org/licenses/by-sa/4.0/ CC BY-SA 4.0
 */

/**
 * Part 1. General purpose library
 */

trait Container
{
    protected $container = [];

    public function __construct(array $data = [])
    {
        $this->container = $data;
    }

    public function offsetGet($key)
    {
        return $this->container[$key];
    }

    public function offsetSet($key, $value): void
    {
        $this->container[$key] = $value;
    }

    public function offsetExists($key)
    {
        return array_key_exists($key, $this->container);
    }

    public function offsetUnset($key): void
    {
        unset($this->container[$key]);
    }
}

/**
 * Part 2. Classes for fixed point arithmetic
 */

interface FixedPointImmutable
{
    const ERR_NEGATIVE_DIGITS_COUNT = 'Digits number must not be negative';
    const STR_DOT = '.';
    const STR_MINUS = '-';
    const STR_ZERO = '0';

    public function __construct(string $amount, int $decimalDigits);
    public function getAmount(): string;
    public function getDecimalDigits(): int;
    public function setDecimalDigits(int $newDigits): FixedPointImmutable;
    public function add(FixedPointImmutable $operand): FixedPointImmutable;
    public function sub(FixedPointImmutable $operand): FixedPointImmutable;
    public function mul(float $operand): FixedPointImmutable;
    public function div(float $operand): FixedPointImmutable;
    public function __toString(): string;
}

trait FixedPointDecimalDigits
{
    private $decimalDigits;

    public function getDecimalDigits(): int
    {
        return $this->decimalDigits;
    }

    public function setDecimalDigits(int $newDigits): FixedPointImmutable
    {
        if ($this->getDecimalDigits() == $newDigits) {
            return $this;
        }

        return new self($this->getAmount(), $newDigits);
    }
}

trait FixedPointToString
{
    public function __toString(): string
    {
        return $this->getAmount();
    }
}

class NaiveFixedPoint implements FixedPointImmutable
{
    use FixedPointDecimalDigits, FixedPointToString;

    private $amountMultiplied;

    public function __construct(string $amount, int $decimalDigits)
    {
        if ($decimalDigits < 0) {
            throw new RangeException(self::ERR_NEGATIVE_DIGITS_COUNT);
        }

        $this->decimalDigits = $decimalDigits;
        $this->amountMultiplied = $this->convertToInt($amount, $decimalDigits);
    }

    public function getAmount(): string
    {
        return $this->convertToString(
            $this->amountMultiplied,
            $this->decimalDigits
        );
    }

    public function add(FixedPointImmutable $operand): FixedPointImmutable
    {
        $digits = max($this->getDecimalDigits(), $operand->getDecimalDigits());

        $amountLeft = $this->amountMultiplied;
        if ($this->getDecimalDigits() < $digits) {
            $amountLeft *= 10 ** ($digits - $this->getDecimalDigits());
        }

        $amountRight = $this->convertToInt($operand->getAmount(), $digits);

        return new self(
            $this->convertToString($amountLeft + $amountRight, $digits),
            $digits
        );
    }

    public function sub(FixedPointImmutable $operand): FixedPointImmutable
    {
        $amount = $operand->getAmount();
        if (substr($amount, 0, 1) == self::STR_MINUS) {
            $amountInv = substr($amount, 1);
        } else {
            $amountInv = self::STR_MINUS . $amount;
        }

        $operandInv = new self($amountInv, $operand->getDecimalDigits());

        return $this->add($operandInv);
    }

    public function mul(float $operand): FixedPointImmutable
    {
        return new self(
            $this->convertToString(
                $this->amountMultiplied * $operand,
                $this->getDecimalDigits()
            ),
            $this->getDecimalDigits()
        );
    }

    public function div(float $operand): FixedPointImmutable
    {
        return $this->mul(1 / $operand);
    }

    private function convertToInt(string $amount, int $decimalDigits): int
    {
        if (strpos($amount, self::STR_DOT) === false) {
            [$intPart, $fractionalPart] = [$amount, 0];
        } else {
            [$intPart, $fractionalPart] = explode(self::STR_DOT, $amount, 2);
            $fractional = str_pad(
                substr($fractionalPart, 0, $decimalDigits),
                $decimalDigits,
                self::STR_ZERO
            );
            $tail = substr($fractionalPart, $decimalDigits + 1);
            if ((int) substr($tail, 0, 1) >= 5) {
                $fractional += 1;
            }
        }

        $result = (int) $intPart * 10 ** $decimalDigits;
        if ($result >= 0) {
            $result += $fractional;
        } else {
            $result -= $fractional;
        }

        return $result;
    }

    private function convertToString(
        int $amountMultiplied,
        int $decimalDigits
    ): string {
        if (!$decimalDigits) {
            return (string) $amountMultiplied;
        }

        $sign = ($amountMultiplied < 0) ? self::STR_MINUS : '';
        $amount = (string) abs($amountMultiplied);

        $intPart = substr($amount, 0, -$decimalDigits);
        if (empty($intPart)) {
            $intPart = self::STR_ZERO;
        }

        $fractionalPart = str_pad(
            substr($amount, -$decimalDigits),
            $decimalDigits,
            self::STR_ZERO,
            STR_PAD_LEFT
        );

        return $sign . $intPart . self::STR_DOT . $fractionalPart;
    }
}

class BcmathFixedPoint implements FixedPointImmutable
{
    use FixedPointDecimalDigits, FixedPointToString;

    private $amount;

    public function __construct(string $amount, int $decimalDigits)
    {
        if ($decimalDigits < 0) {
            throw new RangeException(self::ERR_NEGATIVE_DIGITS_COUNT);
        }

        $roundFix = 5 * 0.1 ** ($decimalDigits + 1);
        if (substr($amount, 0, 1) == self::STR_MINUS) {
            $this->amount = bcsub($amount, $roundFix, $decimalDigits);
        } else {
            $this->amount = bcadd($amount, $roundFix, $decimalDigits);
        }
        $this->decimalDigits = $decimalDigits;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function add(FixedPointImmutable $operand): FixedPointImmutable
    {
        $digits = max($this->getDecimalDigits(), $operand->getDecimalDigits());

        return new self(
            bcadd($this->amount, $operand->getAmount(), $digits),
            $digits
        );
    }

    public function sub(FixedPointImmutable $operand): FixedPointImmutable
    {
        $digits = max($this->getDecimalDigits(), $operand->getDecimalDigits());

        return new self(
            bcsub($this->amount, $operand->getAmount(), $digits),
            $digits
        );
    }

    public function mul(float $operand): FixedPointImmutable
    {
        return new self(
            bcmul($this->amount, $operand, $this->getDecimalDigits() + 1),
            $this->getDecimalDigits()
        );
    }

    public function div(float $operand): FixedPointImmutable
    {
        return new self(
            bcdiv($this->amount, $operand, $this->getDecimalDigits() + 1),
            $this->getDecimalDigits()
        );
    }
}

interface FixedPointFactory
{
    public function create(
        string $amount,
        int $decimalDigits
    ): FixedPointImmutable;
}

class NaiveFixedPointFactory implements FixedPointFactory
{
    public function create(
        string $amount,
        int $decimalDigits
    ): FixedPointImmutable {
        return new NaiveFixedPoint($amount, $decimalDigits);
    }
}

class BcmathFixedPointFactory implements FixedPointFactory
{
    public function create(
        string $amount,
        int $decimalDigits
    ): FixedPointImmutable {
        return new BcmathFixedPoint($amount, $decimalDigits);
    }
}

/**
 * Part 3. Classes for currency and money
 */

class Currency
{
    private $literalCode;
    private $numericCode;
    private $decimalDigits;
    private $name;

    public function __construct(
        string $literalCode,
        ?int $numericCode,
        ?int $decimalDigits,
        string $name
    ) {
        $this->literalCode = $literalCode;
        $this->numericCode = $numericCode;
        $this->decimalDigits = $decimalDigits;
        $this->name = $name;
    }

    public function getLiteralCode(): string
    {
        return $this->literalCode;
    }

    public function getNumericCode(): ?int
    {
        return $this->numericCode;
    }

    public function getDecimalDigits(): ?int
    {
        return $this->decimalDigits;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class MoneyImmutable
{
    private $amount;
    private $currency;

    const DEFAULT_PRECISION = 8;
    const STR_FALLBACK_FORMAT = '%s %s';
    const ERR_CURRENCY_MISMATCH = 'Currency mismatch: %s != %s';

    public function __construct(FixedPointImmutable $amount, Currency $currency)
    {
        $this->currency = $currency;
        $this->amount = $amount->setDecimalDigits(
            $currency->getDecimalDigits() ?? self::DEFAULT_PRECISION
        );
    }

    public function getAmount(): FixedPointImmutable
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function add(MoneyImmutable $operand): MoneyImmutable
    {
        $this->assertSameCurrency($operand);

        return new self(
            $this->amount->add($operand->getAmount()),
            $this->currency
        );
    }

    public function sub(MoneyImmutable $operand): MoneyImmutable
    {
        $this->assertSameCurrency($operand);

        return new self(
            $this->amount->sub($operand->getAmount()),
            $this->currency
        );
    }

    public function mul(float $operand): MoneyImmutable
    {
        return new self($this->amount->mul($operand), $this->currency);
    }

    public function div(float $operand): MoneyImmutable
    {
        return new self($this->amount->div($operand), $this->currency);
    }

    public function __toString(): string
    {
        return sprintf(
            self::STR_FALLBACK_FORMAT,
            $this->currency->getLiteralCode(),
            $this->getAmount()->getAmount() // XXX
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->getCurrency() !== $this->currency) {
            throw new InvalidArgumentException(
                sprintf(
                    self::ERR_CURRENCY_MISMATCH,
                    $this->currency->getLiteralCode(),
                    $other->getCurrency()->getLiteralCode()
                )
            );
        }
    }
}

class MoneyFactory
{
    private $fixedPointFactory;
    private $currency;

    public function __construct(
        FixedPointFactory $fixedPointFactory,
        Currency $currency
    ) {
        $this->fixedPointFactory = $fixedPointFactory;
        $this->currency = $currency;
    }

    public function create(string $amount): MoneyImmutable
    {
        return new MoneyImmutable(
            $this->fixedPointFactory->create(
                $amount,
                $this->currency->getDecimalDigits() ??
                    MoneyImmutable::DEFAULT_PRECISION
            ),
            $this->currency
        );
    }
}

abstract class AbstractLoanAmortizationSchedule
{
    protected $moneyFactory;

    const KEY_BALANCE_START = 'balance_start';
    const KEY_INTEREST = 'interest';
    const KEY_PAYMENT = 'payment';
    const KEY_BALANCE_END = 'balance_end';

    public function __construct(MoneyFactory $moneyFactory)
    {
        $this->moneyFactory = $moneyFactory;
    }

    abstract public function getAmortizationSchedule(
        string $amount,
        float $ratePerPeriod,
        int $periods
    ): array;
}

class AnnuityLoanAmortizationSchedule extends AbstractLoanAmortizationSchedule
{
    public function getAmortizationSchedule(
        string $amount,
        float $ratePerPeriod,
        int $periods
    ): array {
        $loanAmount = $this->moneyFactory->create($amount);

        $annuityFactor =
            $ratePerPeriod * (1 + $ratePerPeriod) ** $periods
            / ((1 + $ratePerPeriod) ** $periods - 1);

        $paymentPerPeriod = $loanAmount->mul($annuityFactor);

        $schedule = [];
        $balance = $loanAmount;
        for ($n = 1; $n <= $periods; $n++) {
            $interest = $balance->mul($ratePerPeriod);
            $endBalance = $balance->add($interest)->sub($paymentPerPeriod);
            $payment = $paymentPerPeriod;
            if ($n == $periods) {
                $payment = $payment->add($endBalance);
                $endBalance = $endBalance->mul(0);
            }
            $schedule[] = [
                self::KEY_BALANCE_START => $balance,
                self::KEY_INTEREST => $interest,
                self::KEY_PAYMENT => $payment,
                self::KEY_BALANCE_END => $endBalance,
            ];
            $balance = $endBalance;
        }

        return $schedule;
    }
}

class LinearLoanAmortizationSchedule extends AbstractLoanAmortizationSchedule
{
    public function getAmortizationSchedule(
        string $amount,
        float $ratePerPeriod,
        int $periods
    ): array {
        $loanAmount = $this->moneyFactory->create($amount);

        $schedule = [];
        $balance = $loanAmount;
        for ($n = 1; $n <= $periods; $n++) {
            $interest = $balance->mul($ratePerPeriod);
            $payment = $loanAmount->div($periods)->add($interest);
            $endBalance = $balance->add($interest)->sub($payment);
            if ($n == $periods) {
                $payment = $payment->add($endBalance);
                $endBalance = $endBalance->mul(0);
            }
            $schedule[] = [
                self::KEY_BALANCE_START => $balance,
                self::KEY_INTEREST => $interest,
                self::KEY_PAYMENT => $payment,
                self::KEY_BALANCE_END => $endBalance,
            ];
            $balance = $endBalance;
        }

        return $schedule;
    }
}

/**
 * Part 4. Application, request and responce
 */

class Application implements ArrayAccess
{
    use Container;

    protected $cache = [];

    const ERR_ELEMENT_NOT_DEFINED = 'Element %s is nod defined';

    public function offsetGet($key)
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (!array_key_exists($key, $this->container)) {
            throw new InvalidArgumentException(
                sprintf(
                    self::ERR_ELEMENT_NOT_DEFINED,
                    $key
                )
            );
        }

        if (is_callable($this->container[$key])) {
            $this->cache[$key] = $this->container[$key]($this);

            return $this->cache[$key];
        }

        return $this->container[$key];
    }

    public function offsetUnset($key): void
    {
        unset($this->container[$key]);
        unset($this->cache[$key]);
    }
}

class Request implements ArrayAccess
{
    use Container;

    public static function createFromGlobals(): self
    {
        return new self($_GET);
    }
}

interface Responce
{
    public function sendHeaders();
}

/**
 * Part 5. Templating library
 */

interface Renderable
{
    public function render();
}

abstract class AbstractTemplate implements Renderable, ArrayAccess
{
    use Container;

    const KEY_I18N = 'translate';
    const KEY_URL_PARAM = 'url_param';
    const STR_URL_FORMAT = '?%s';
    const STR_SPACE = ' ';
    const STR_TAG_OPEN = '<%s>';
    const STR_TAG_CLOSE = '</%s>';

    abstract public function render();

    public function __toString(): string
    {
        ob_start();
        $this->render();
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    protected function getUrl(array $parameters)
    {
        $currentParameters = $this[self::KEY_URL_PARAM] ?? [];
        $newParameters = $parameters + $currentParameters;

        ksort($currentParameters);
        ksort($newParameters);
        if ($currentParameters == $newParameters) {
            // should not link to current page
            return false;
        }

        return sprintf(self::STR_URL_FORMAT, http_build_query($newParameters));
    }

    protected function say(string $message, ?string $tag = null): void
    {
        if ($tag) {
            printf(self::STR_TAG_OPEN, $tag);
        }
        echo htmlspecialchars($message);
        if ($tag) {
            [$tag] = explode(self::STR_SPACE, $tag);
            printf(self::STR_TAG_CLOSE, $tag);
        }
    }

    protected function getMsg(string $message, ...$args): string
    {
        return sprintf($this[self::KEY_I18N][$message] ?? $message, ...$args);
    }

    protected function sayMsg(
        string $message,
        ?string $tag = null,
        ...$args
    ): void {
        $this->say($this->getMsg($message, ...$args), $tag);
    }

    protected function sayVar($key, $default = '', ?string $tag = null): void
    {
        $this->say($this->container[$key] ?? $default, $tag);
    }
}

/**
 * Part 6. Unit tests
 */

class TestFailed extends Exception
{
}

abstract class AbstractTest
{
    protected $alreadyTested = false;
    protected $assertionsPassed = 0;
    protected $assertionsFailed = 0;
    protected $failLog = [];

    const STR_TEST_PATTERN = '/^test[A-Z]/';
    const ERR_NOT_EQUAL = '%s is not equal to %s';
    const ERR_NOT_TRUE = 'Argument is not true';
    const KEY_METHOD = 'method';
    const KEY_FILE = 'file';
    const KEY_LINE = 'line';
    const KEY_MESSAGE = 'message';

    public function getAssertionsPassed()
    {
        return $this->assertionsPassed;
    }

    public function getAssertionsFailed()
    {
        return $this->assertionsFailed;
    }

    public function getFailLog()
    {
        return $this->failLog;
    }

    public function runTests(): void
    {
        if ($this->alreadyTested) {
            return;
        }

        foreach ($this->getTestMethods() as $method) {
            try {
                $this->$method();
            } catch (TestFailed $fail) {
                $this->failLog[] = [
                    self::KEY_METHOD => $method,
                    self::KEY_FILE => $fail->getFile(),
                    self::KEY_LINE => $fail->getLine(),
                    self::KEY_MESSAGE => $fail->getMessage(),
                ];
            }
        }
        $this->alreadyTested = true;
    }

    protected function getTestMethods()
    {
        foreach (get_class_methods($this) as $method) {
            if (preg_match(self::STR_TEST_PATTERN, $method)) {
                yield $method;
            }
        }
    }

    protected function assertTrue($arg, string $message = null): void
    {
        if ($arg) {
            $this->assertionsPassed++;
        } else {
            $this->assertionsFailed++;
            throw new TestFailed($message ?? self::ERR_NOT_TRUE);
        }
    }

    protected function assertEqual($arg1, $arg2, string $message = null): void
    {
        if ($arg1 == $arg2) {
            $this->assertionsPassed++;
        } else {
            $this->assertionsFailed++;
            throw new TestFailed(
                sprintf(
                    $message ?? self::ERR_NOT_EQUAL,
                    json_encode($arg1),
                    json_encode($arg2)
                )
            );
        }
    }
}

abstract class AbstractTestFixedPoint extends AbstractTest
{
    protected $fixedPointFactory;

    protected function testCreateSimple()
    {
        $test = $this->fixedPointFactory->create('123.45', 2);
        $this->assertTrue($test instanceof FixedPointImmutable);
        $this->assertEqual($test->getAmount(), '123.45');
        $this->assertEqual($test->getDecimalDigits(), 2);
    }

    protected function testCreateNegative()
    {
        $test = $this->fixedPointFactory->create('-123.45', 2);
        $this->assertTrue($test instanceof FixedPointImmutable);
        $this->assertEqual($test->getAmount(), '-123.45');
    }

    protected function testZeroPadding()
    {
        $test = $this->fixedPointFactory->create('.00100', 5);
        $this->assertTrue($test instanceof FixedPointImmutable);
        $this->assertEqual($test->getAmount(), '0.00100');
    }

    protected function testRounding()
    {
        $test = $this->fixedPointFactory->create('0.999999', 2);
        $this->assertEqual($test->getAmount(), '1.00');
    }

    protected function testAdd()
    {
        $test1 = $this->fixedPointFactory->create('12.34', 2);
        $test2 = $test1->add($test1);
        $this->assertEqual($test2->getAmount(), '24.68');

        $test3 = $this->fixedPointFactory->create('0.01', 2);
        $test4 = $test2->add($test3);
        $this->assertEqual($test4->getAmount(), '24.69');
    }

    protected function testSub()
    {
        $test1 = $this->fixedPointFactory->create('12.34', 2);
        $test2 = $test1->sub($test1);
        $this->assertEqual($test2->getAmount(), '0.00');

        $test3 = $test2->sub($test1);
        $this->assertEqual($test3->getAmount(), '-12.34');
    }

    protected function testMul()
    {
        $test1 = $this->fixedPointFactory->create('12.34', 2);
        $test2 = $test1->mul(2);
        $this->assertEqual($test2->getAmount(), '24.68');
    }

    protected function testDiv()
    {
        $test1 = $this->fixedPointFactory->create('10.00', 2);
        $test2 = $test1->div(2);
        $this->assertEqual($test2->getAmount(), '5.00');

        $test3 = $test1->div(3);
        $this->assertEqual($test3->getAmount(), '3.33');
    }
}

class TestNaiveFixedPoint extends AbstractTestFixedPoint
{
    public function __construct()
    {
        $this->fixedPointFactory = new NaiveFixedPointFactory;
    }
}

class TestBcmathFixedPoint extends AbstractTestFixedPoint
{
    public function __construct()
    {
        $this->fixedPointFactory = new BcmathFixedPointFactory;
    }
}

/**
 * Part 7. Templates
 */

abstract class GenericLayout extends AbstractTemplate implements Responce
{
    protected $headers = [
        'Content-type: text/html; charset=UTF-8',
    ];

    public function sendHeaders(): AbstractTemplate
    {
        foreach ($this->headers as $header) {
            header($header);
        }

        return $this;
    }

    abstract protected function renderContentBlock();

    public function render()
    {
?><!DOCTYPE html>
<html lang="<?php $this->sayVar('lang', 'en'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->sayMsg('Loan calculator'); ?></title>
    <style>
        body { max-width: 55em; margin: 0 auto; padding: 1em }
        nav+article, article+footer { margin: 1em 0 0 0 }
        footer { border-top: thin solid }
        .aside { float: right }
        nav li { display: inline }
        nav ul, nav li { margin: 0; padding: 0; list-style-type: none }
        nav li+li { margin-left: .5em }
        h1, h2 { font-weight: normal }
        h1 { font-size: 200%; margin: .5em 0 }
        h2 { font-size: 150%; margin: .6666em 0 }
        .error { padding: 1em; border: dotted thin }
        table { width: 100% }
        table, th, td { border: dotted thin; border-collapse: collapse }
        .four-equal-col td { width: 25% }
        .schedule td { text-align: right }
        th, td { padding: .5em }
        input[type="text"] { width: 100% }
    </style>
</head>
<body>
    <?php $this->renderMenuBlock(); ?>
    <article>
        <?php $this->renderContentBlock(); ?>
    </article>
    <footer>
        © <a href="https://gist.github.com/kastaneda"><?php
            $this->sayMsg('Dmitry Kolesnikov'); ?></a>
    </footer>
</body>
</html><?php
    }

    protected function renderMenuBlock()
    {
        if (isset($this['nav.aside'])) {
            echo '<nav class="aside">';
            $this->linkListRenderHelper($this['nav.aside']);
            echo '</nav>';
        }

        if (isset($this['nav.main'])) {
            echo '<nav class="main">';
            $this->linkListRenderHelper($this['nav.main']);
            echo '</nav>';
        }
    }

    protected function linkListRenderHelper($linkList)
    {
        echo '<ul>';
        foreach ($linkList as $item) {
            [$param, $label] = $item;
            $url = is_scalar($param) ? $param : $this->getUrl($param);
            if ($url) {
                echo '<li><a href="' . htmlspecialchars($url) . '">';
                $this->sayMsg($label);
                echo '</a></li>';
            } else {
                echo '<li><strong>';
                $this->sayMsg($label);
                echo '</strong></li>';
            }
        }
        echo '</ul>';
    }

    protected function sayMoney(MoneyImmutable $money)
    {
        if ($format = ($this['currencyFormat'] ?? false)) {
            $amount = $money->getAmount();
            [$intPart, $fractionalPart] =
                explode(FixedPointImmutable::STR_DOT, $amount);

            $intPart = trim(strrev(
                chunk_split(strrev($intPart), 3, self::STR_SPACE)
            ));

            echo htmlspecialchars(sprintf($format, $intPart, $fractionalPart));
        } else {
            // fallback
            echo htmlspecialchars((string) $money);
        }
    }

    protected function selectOptionHelper(
        string $value,
        string $message,
        bool $selected
    ): void {
        $tag = '<option value="%s">%s</option>';
        if ($selected) {
            $tag = '<option value="%s" selected>%s</option>';
        }
        printf($tag, htmlspecialchars($value), htmlspecialchars($message));
    }

    protected function inputTypeRadioHelper(string $name, string $value): void
    {
        $tag = '<input type="radio" name="%s" value="%s">';
        if (isset($this[$name]) && $this[$name] == $value) {
            $tag = '<input type="radio" name="%s" value="%s" checked>';
        }
        printf($tag, htmlspecialchars($name), htmlspecialchars($value));
    }
}

class LoanCalculatorPage extends GenericLayout
{
    protected function renderContentBlock()
    {
        $this->sayMsg('Loan calculator', 'h1'); ?>

<form method="get">
    <table class="four-equal-col">
        <tr>
            <th><?php $this->sayMsg('Amount of loan'); ?></th>
            <th><?php $this->sayMsg('Annual interest rate'); ?></th>
            <th><?php $this->sayMsg('Loan fee'); ?></th>
            <th><?php $this->sayMsg('Duration of the loan'); ?></th>
        </tr>
        <tr>
            <td><input type="text" name="amount"
                value="<?php $this->sayVar('amount'); ?>"></td>
            <td><input type="text" name="rate"
                value="<?php $this->sayVar('rate'); ?>"
                placeholder="<?php $this->sayMsg('percents'); ?>"
                title="<?php $this->sayMsg('percents'); ?>"></td>
            <td><input type="text" name="fee"
                value="<?php $this->sayVar('fee'); ?>"
                placeholder="<?php $this->sayMsg('percents'); ?>"
                title="<?php $this->sayMsg('percents'); ?>"></td>
            <td><input type="number" name="months"
                value="<?php $this->sayVar('months'); ?>"
                placeholder="<?php $this->sayMsg('months'); ?>"
                title="<?php $this->sayMsg('months'); ?>"></td>
        </tr>
    </table>
    <?php if (isset($this['currency']) && isset($this['defaultCurrency'])): ?>
    <p class="aside">
        <select name="currency"><?php
            foreach ($this['currency'] as $currency) {
                $this->selectOptionHelper(
                    $currency->getLiteralCode(),
                    $this->getMsg($currency->getName()),
                    $currency == $this['defaultCurrency']
                );
            } ?></select>
    </p>
    <?php endif; ?>
    <p>
        <label>
            <?php $this->inputTypeRadioHelper('type', 'annuity'); ?>
            <?php $this->sayMsg('Annuity payments'); ?>
        </label><br>
        <label>
            <?php $this->inputTypeRadioHelper('type', 'linear'); ?>
            <?php $this->sayMsg('Linear payments'); ?>
        </label>
    </p>
    <p>
        <input type="hidden" name="page" value="calculator">
        <input type="hidden" name="lang"
            value="<?php $this->sayVar('lang', 'en'); ?>">
        <input type="submit" value="<?php
            $this->sayMsg('Calculate amortization schedule'); ?>">
    </p>
</form>

<?php if (isset($this['schedule'])): ?>
    <h2><?php $this->sayMsg('Amortization schedule'); ?></h2>
    <table class="schedule four-equal-col">
        <tr>
            <th><?php $this->sayMsg('Beginning balance'); ?></th>
            <th><?php $this->sayMsg('Interest'); ?></th>
            <th><?php $this->sayMsg('Payment'); ?></th>
            <th><?php $this->sayMsg('Ending balance'); ?></th>
        </tr>
        <?php foreach ($this['schedule'] as $row): ?>
        <tr>
            <td><?php $this->sayMoney($row['balance_start']); ?></td>
            <td><?php $this->sayMoney($row['interest']); ?></td>
            <td><?php $this->sayMoney($row['payment']); ?></td>
            <td><?php $this->sayMoney($row['balance_end']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php
    }
}

class SelfTestPage extends GenericLayout
{
    protected function renderContentBlock()
    {
        $tests = $this['tests'] ?? [];

        $this->sayMsg('Self-test', 'h1');

        if (!count($tests)) {
            $this->sayMsg('No tests defined', 'p class="error"');
            return;
        }

        foreach ($tests as $test) { ?>
            <h2>
                <?php $this->sayMsg('Class'); ?>:
                <code><?php $this->say(get_class($test)); ?></code>
            </h2>
            <p>
                <?php $this->sayMsg(
                    'Assertions passed: %d, failed: %d.',
                    null,
                    $test->getAssertionsPassed(),
                    $test->getAssertionsFailed()
                ); ?>
            </p><?php
            if ($test->getAssertionsFailed()) {
                echo '<ul>';
                foreach ($test->getFailLog() as $row) {
                    $this->say(
                        $row[AbstractTest::KEY_METHOD] . ': ' .
                        $row[AbstractTest::KEY_MESSAGE],
                        'li'
                    );
                }
                echo '</ul>';
            }
        }
    }
}

class ShowSourcePage extends GenericLayout
{
    protected function renderContentBlock()
    {
        $this->sayMsg('Source code', 'h1');
        show_source(__FILE__);
    }
}

class ErrorPage extends GenericLayout
{
    protected function renderContentBlock()
    {
        $this->sayMsg('Something went wrong', 'h1');

        if (isset($this['message'])) {
            $this->say($this['message'], 'p class="error"');
        }

        if (isset($this['exception'])) {
            $this->say($this['exception'], 'pre class="error"');
        }
    }
}

/**
 * Part 8. Runtime configuration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$app = new Application();

// ISO 4217
$app['currency'] = [
    'UAH' => new Currency('UAH', 980, 2, 'Ukrainian hryvnia'),

    // Major reserve currencies
    'USD' => new Currency('USD', 840, 2, 'United States dollar'),
    'EUR' => new Currency('EUR', 978, 2, 'Euro'),

    // Other reserve currencies
    'GBP' => new Currency('GBP', 826, 2, 'Pound sterling'),
    'JPY' => new Currency('JPY', 392, 0, 'Japanese yen'),
    'CHF' => new Currency('CHF', 756, 2, 'Swiss franc'),
    'CAD' => new Currency('CAD', 124, 2, 'Canadian dollar'),
    'CNY' => new Currency('CNY', 156, 2, 'Chinese yuan'),

    // Precious metals
    'XAU' => new Currency('XAU', 959, null, 'Gold'),
    'XAG' => new Currency('XAG', 961, null, 'Silver'),

    // Cryptocurrencies
    'XBT' => new Currency('XBT', null, 8, 'Bitcoin'),
];

$app['supportedLocales'] = ['en', 'uk'];

$app['translate'] = [
    'uk' => [
        'Dmitry Kolesnikov' => 'Дмитро Колесников',
        'Loan calculator' => 'Кредитний калькулятор',
        'Self-test' => 'Самоперевірка',
        'Source code' => 'Програмний код',
        'No tests defined' => 'Тести не визначені',
        'Class' => 'Клас',
        'Assertions passed: %d, failed: %d.' =>
            'Тверджень перевірено: %d, провалено: %d.',
        'Amount of loan' => 'Сума кредиту',
        'Annual interest rate' => 'Річна відсоткова ставка',
        'Loan fee' => 'Плата за кредит',
        'Duration of the loan' => 'Тривалість позики',
        'percents' => 'відсотки',
        'months' => 'місяці',
        'Calculate amortization schedule' => 'Розрахувати графік погашення',
        'Annuity payments' => 'Аннуітетні платежі',
        'Linear payments' => 'Лінійні платежі',
        'Amortization schedule' => 'Графік погашення',
        'Beginning balance' => 'Початковий баланс',
        'Interest' => 'Сума відсотків',
        'Payment' => 'Платіж',
        'Ending balance' => 'Кінцевий баланс',
        'Ukrainian hryvnia' => 'Гривня',
        'United States dollar' => 'Долар США',
        'Euro' => 'Євро',
        'Pound sterling' => 'Фунт стерлінгів',
        'Japanese yen' => 'Єна',
        'Swiss franc' => 'Швейцарський франк',
        'Canadian dollar' => 'Канадський долар',
        'Chinese yuan' => 'Юань',
        'Gold' => 'Золото',
        'Silver' => 'Срібло',
        'Bitcoin' => 'Біткойн',
    ],
];

$app['locale'] = [
    'uk' => [
        'defaultCurrency' => 'UAH',
        'currencyFormat' => [
            'UAH' => '%s,%s грн',
            'EUR' => '%s,%s €',
            'USD' => '%s,%s $',
            'GBP' => '%s,%s £',
        ],
    ],
    'en' => [
        'defaultCurrency' => 'USD',
        'currencyFormat' => [
            'EUR' => '€%s.%s',
            'USD' => '$%s.%s',
            'GBP' => '£%s.%s',
        ],
    ],
];

$app['defaultLang'] = function (Application $app) {
    $lang = $app['request']['lang'] ?? 'en';
    if (in_array($lang, $app['supportedLocales'])) {
        return $lang;
    }

    return 'en';
};

$app['defaultCurrency'] = function (Application $app) {
    if (isset($app['request']['currency'])) {
        $code = $app['request']['currency'];
        if (isset($app['currency'][$code])) {
            return $app['currency'][$code];
        }
    }

    $code = 'EUR';
    $lang = $app['defaultLang'];
    $code = $app['locale'][$lang]['defaultCurrency'] ?? $code;

    return $app['currency'][$code];
};

$app['currencyFormat'] = function (Application $app) {
    $lang = $app['defaultLang'];
    $code = $app['defaultCurrency']->getLiteralCode();

    return $app['locale'][$lang]['currencyFormat'][$code] ?? false;
};

$app['moneyFactory'] = function (Application $app) {
    return new MoneyFactory($app['fixedPointFactory'], $app['defaultCurrency']);
};

$app['fixedPointFactory'] = function (Application $app) {
    if (extension_loaded('bcmath')) {
        return new BcmathFixedPointFactory();
    } else {
        return new NaiveFixedPointFactory();
    }
};

$app['testSuite'] = function (Application $app) {
    return [
        new TestNaiveFixedPoint(),
        new TestBcmathFixedPoint(),
    ];
};

$app['data.lang'] = function (Application $app) {
    $lang = $app['defaultLang'];

    return [
        'lang' => $lang,
        'url_param' => ['lang' => $lang],
        'translate' => $app['translate'][$lang] ?? [],
    ];
};

$app['data.nav'] = [
    'nav.main' => [
        [['page' => 'calculator'], 'Loan calculator'],
        [['page' => 'self-test'], 'Self-test'],
        [['page' => 'source-code'], 'Source code'],
    ],
    'nav.aside' => [
        [['lang' => 'en'], 'English'],
        [['lang' => 'uk'], 'Українська'],
    ],
];

$app['schedule.annuity'] = function (Application $app) {
    return new AnnuityLoanAmortizationSchedule($app['moneyFactory']);
};

$app['schedule.linear'] = function (Application $app) {
    return new LinearLoanAmortizationSchedule($app['moneyFactory']);
};

$app['request'] = function (Application $app) {
    return Request::createFromGlobals();
};

$app['responce'] = function (Application $app) {
    $data = [];
    $data += $app['data.lang'] ?? [];
    $data += $app['data.nav'] ?? [];

    $pageName = $app['request']['page'] ?? 'calculator';
    $data['url_param']['page'] = $pageName;

    switch ($pageName) {
        case 'calculator':
            foreach (['amount', 'rate', 'fee', 'months'] as $key) {
                $value = $app['request'][$key] ?? '';
                if (is_numeric($value)) {
                    $data[$key] = $value;
                }
            }
            $type = $app['request']['type'] ?? 'annuity';
            if (in_array($type, ['annuity', 'linear'])) {
                $data['type'] = $type;
                if ($scheduleClass = $app['schedule.' . $type]) {
                    $fee = $data['fee'] ?? 0;
                    $amount = ($data['amount'] ?? 0) * (1 + $fee / 100);
                    $rate = $data['rate'] ?? false;
                    $months = $data['months'] ?? false;
                    if ($amount > 0 && $rate > 0 && $months > 0) {
                        $data['schedule'] = $scheduleClass
                            ->getAmortizationSchedule(
                                $amount,
                                $rate / 12 / 100,
                                $months
                            );
                    }
                }
            }
            $data['currency'] = $app['currency'];
            $data['currencyFormat'] = $app['currencyFormat'];
            $data['defaultCurrency'] = $app['defaultCurrency'];
            $page = new LoanCalculatorPage($data);
            break;

        case 'source-code':
            $page = new ShowSourcePage($data);
            break;

        case 'self-test':
            $data['tests'] = $app['testSuite'] ?? [];
            foreach ($data['tests'] as $test) {
                $test->runTests();
            }
            $page = new SelfTestPage($data);
            break;

        default:
            $data += ['message' => 'Page not found'];
            $page = new ErrorPage($data);
            break;
    }

    return $page;
};

/**
 * Part 9. Front controller
 */

try {
    $app['responce']->sendHeaders()->render();
} catch (Exception $exception) {
    $page = new ErrorPage(['exception' => $exception]);
    $page->sendHeaders()->render();
}
