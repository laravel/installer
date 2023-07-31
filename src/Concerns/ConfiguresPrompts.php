<?php

namespace Laravel\Installer\Console\Concerns;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ConfiguresPrompts
{
    /**
     * Configure the prompt fallbacks.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function configurePrompts(InputInterface $input, OutputInterface $output)
    {
        Prompt::fallbackWhen(! $input->isInteractive() || PHP_OS_FAMILY === 'Windows');

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->ask($prompt->label, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate,
            $output
        ));

        PasswordPrompt::fallbackUsing(fn (PasswordPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->askHidden($prompt->label) ?? '',
            $prompt->required,
            $prompt->validate,
            $output
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->confirm($prompt->label, $prompt->default),
            $prompt->required,
            $prompt->validate,
            $output
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt) => $this->promptUntilValid(
            fn () => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate,
            $output
        ));

        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) use ($input, $output) {
            if ($prompt->default !== []) {
                return $this->promptUntilValid(
                    fn () => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, implode(',', $prompt->default), true),
                    $prompt->required,
                    $prompt->validate,
                    $output
                );
            }

            return $this->promptUntilValid(
                fn () => collect((new SymfonyStyle($input, $output))->choice(
                    $prompt->label,
                    array_is_list($prompt->options)
                        ? ['None', ...$prompt->options]
                        : ['none' => 'None', ...$prompt->options],
                    'None',
                    true)
                )->reject(array_is_list($prompt->options) ? 'None' : 'none')->all(),
                $prompt->required,
                $prompt->validate,
                $output
            );
        });

        SuggestPrompt::fallbackUsing(fn (SuggestPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt, $input, $output) {
                $question = new Question($prompt->label, $prompt->default);

                is_callable($prompt->options)
                    ? $question->setAutocompleterCallback($prompt->options)
                    : $question->setAutocompleterValues($prompt->options);

                return (new SymfonyStyle($input, $output))->askQuestion($question);
            },
            $prompt->required,
            $prompt->validate,
            $output
        ));
    }

    /**
     * Prompt the user until the given validation callback passes.
     *
     * @param  \Closure  $prompt
     * @param  bool|string  $required
     * @param  \Closure|null  $validate
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function promptUntilValid($prompt, $required, $validate, $output)
    {
        while (true) {
            $result = $prompt();

            if ($required && ($result === '' || $result === [] || $result === false)) {
                $output->writeln('<error>'.(is_string($required) ? $required : 'Required.').'</error>');

                continue;
            }

            if ($validate) {
                $error = $validate($result);

                if (is_string($error) && strlen($error) > 0) {
                    $output->writeln("<error>{$error}</error>");

                    continue;
                }
            }

            return $result;
        }
    }
}
