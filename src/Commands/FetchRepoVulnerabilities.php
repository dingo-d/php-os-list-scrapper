<?php
/**
 * File holding the class with the command generation logic
 *
 * @package Infinum\Commands
 */

declare(strict_types=1);

namespace Infinum\Commands;

use Github\Client;
use RuntimeException;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

/**
 * Fetch repository vulnerabilities class
 *
 * Symfony command generator class used to get all the vulnerabilities for
 * repositories from the organization with a specific tag.
 *
 * Usage: php bin/console os-list:fetch-vulnerabilities
 *
 * @package Infinum\Commands
 */
class FetchRepoVulnerabilities extends FetchCommand
{
	private const NAME = 'Name';
	private const URL = 'URL';
	private const VULNERABILITIES = 'Vulnerabilities';
	private const VULNERABILITIES_INFO = 'Info';
	private const VULNERABILITIES_SEVERITY = 'Severity';
	private const VULNERABILITIES_SUMMARY = 'Summary';
	private const VULNERABILITIES_DESCRIPTION = 'Description';
	private const CURSOR_ID = 'Cursor ID';

	/**
	 * Command name property
	 *
	 * @var string Command name.
	 */
	protected static string $defaultName = 'os-list:repo-vulnerabilities';

	/**
	 * Configures the current command
	 *
	 * @inheritDoc
	 */
	protected function configure(): void
	{
		$this
			->setDescription('Fetches all the repositories in the organization or for an owner')
			->setHelp('This command will generate a table of information for the repositories tagged with a certain tag for a certain owner. Has to be authenticated to work.')
			->addArgument('ghToken', InputArgument::REQUIRED, 'GitHub authentication token. Should be of the format \'ghp_....\'')
			->addArgument('user', InputArgument::REQUIRED, 'User for which you want to fetch the data of. For example: infinum')
			->addArgument('topic', InputArgument::OPTIONAL, 'Set the topic based on which you want to search for. For example: open-source')
			->addArgument('count', InputArgument::OPTIONAL, 'Set the number of repos to fetch. Default is 10')
			->addArgument('cursorId', InputArgument::OPTIONAL, 'Used if you want to paginate results. Usually the last cursor ID from the results. See: https://graphql.org/learn/pagination/#pagination-and-edges for more information')
			->addOption('full', null, InputArgument::OPTIONAL, 'Use to display full description', false);
	}

	/**
	 * Execute the current command
	 *
	 * @param InputInterface $input Input values.
	 * @param OutputInterface $output Output values.
	 *
	 * @return int
	 * @throws RuntimeException Validation exceptions.
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$ghToken = $input->getArgument('ghToken');
		$user = $input->getArgument('user');

		if (empty($ghToken)) {
			throw new RuntimeException('GitHub token empty');
		}

		if (empty($user)) {
			throw new RuntimeException('GitHub user/org empty');
		}

		$topic = $input->getArgument('topic');
		$count = (int) $input->getArgument('count') ?? 10;
		$cursorId = $input->getArgument('cursorId');

		$query = $this->getQuery($user, $count, $topic, $cursorId);

		$client = new Client();
		$client->authenticate($ghToken, null, Client::AUTH_ACCESS_TOKEN);

		$result = $client->api('graphql')->execute($query);

		if (empty($result)) {
			$io->warning('No data to show for the requested owner.');
		}

		$totalNumber = $result['data']['search']['repositoryCount'] ?? 0;

		if (empty($totalNumber)) {
			$io->warning('Query returned no repositories.');
		}

		$repos = $result['data']['search']['repos'];

		$rows = [];

		foreach ($repos as $repo) {
			$name = $repo['repo']['name'] ?? '';
			$url = $repo['repo']['url'] ?? '';
			$cursorId = $repo['cursor'] ?? '';
			$vulnerabilityAlerts = $repo['repo']['vulnerabilityAlerts']['totalCount'] ?? 0;

			if (empty($vulnerabilityAlerts)) {
				continue;
			}

			$securityAlerts = '';

			foreach ($repo['repo']['vulnerabilityAlerts']['nodes'] as $vulnerabilities) {
				$description = $vulnerabilities['securityVulnerability']['advisory']['description'] ?? '';

				if (!$input->getOption('full')) {
					$description = $this->trimWords($description, 20);
				}

				$severity = $vulnerabilities['securityVulnerability']['severity'] ?? '';
				$summary = $vulnerabilities['securityVulnerability']['advisory']['summary'] ?? '';

				$securityAlerts .= 'Severity: ' . $this->colorMessage($severity, $severity) . PHP_EOL;
				$securityAlerts .= 'Summary: ' . $summary . PHP_EOL;
				$securityAlerts .= 'Description: ' . $description . PHP_EOL;
				$securityAlerts .= '------------------------------------------------------------' . PHP_EOL;
			}

			$rows[] = [
				$name,
				$url,
				$vulnerabilityAlerts,
				$securityAlerts,
				$cursorId,
			];
			$rows[] = new TableSeparator();
		}

		$io->success("Total number of repositories found for {$user}: {$totalNumber}");
		$table = new Table($output);
		$table->setHeaders([
			self::NAME,
			self::URL,
			self::VULNERABILITIES,
			self::VULNERABILITIES_INFO,
			self::CURSOR_ID,
		])
			->setRows($rows)
			->setColumnWidths([20, 20, 15, 60, 12])
			->setColumnMaxWidth(3, 60)
			->render();

		return 0;
	}

	/**
	 * GraphQL query for the desired results
	 *
	 * @param string $user User or organization identifier.
	 * @param int $count Number of repos to fetch.
	 * @param string|null $topic Specific topic to fetch. Optional.
	 * @param string|null $cursorId Specific cursor ID to fetch. Used for pagination. Optional.
	 *
	 * @return string GraphQL query string.
	 */
	protected function getQuery(string $user, int $count = 10, ?string $topic = '', ?string $cursorId = ''): string
	{
		if (empty($topic)) {
			$queryString = "user:$user";
		} else {
			$queryString = "user:$user, topic:$topic";
		}

		if (!empty($cursorId)) {
			$queryString .= ", after:$cursorId";
		}

		return <<<QUERY
query {
	search(type: REPOSITORY, first: $count, query: "$queryString") {
		repositoryCount
		 repos: edges {
		  cursor
		  repo: node {
			... on Repository {
			  id
			  name
			  url
			  vulnerabilityAlerts(first: 100) {
				totalCount
				nodes {
				  securityVulnerability {
					advisory {
					  cvss {
						score
					  }
					  severity
					  summary
					  description
					  cwes(first: 10) {
						totalCount
						nodes {
						  cweId
						  name
						  description
						}
					  }
					}
					severity
				  }
				}
			  }
			}
		  }
		}
	}
}
QUERY;
	}
}
