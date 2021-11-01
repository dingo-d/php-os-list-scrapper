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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

/**
 * Fetch repository details class
 *
 * Symfony command generator class used to get all the repositories
 * from the organization with a specific tag.
 *
 * Usage: php bin/console os-list:fetch
 *
 * @package Infinum\Commands
 */
class FetchRepoDetails extends FetchCommand
{
	private const NAME = 'Name';
	private const DESCRIPTION = 'Description';
	private const LICENSE = 'License';
	private const URL = 'URL';
	private const STAR_COUNT = 'Star Count';
	private const FORK_COUNT = 'Fork count';
	private const OPEN_ISSUES = 'Open Issues';
	private const OPEN_PRS = 'Open PRs';
	private const HAS_CODE_OF_CONDUCT = 'Code of Conduct';
	private const HAS_CUSTOM_OG_IMAGE = 'Custom OG Image';
	private const HAS_ISSUE_TEMPLATES = 'Has Issue Templates';
	private const HAS_PR_TEMPLATES = 'Has PR Templates';
	private const VULNERABILITIES = 'Vulnerabilities';
	private const CURSOR_ID = 'Cursor ID';

	/**
	 * Command name property
	 *
	 * @var string Command name.
	 */
	protected static string $defaultName = 'os-list:fetch';

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
			->addOption('fields', null, InputArgument::OPTIONAL, 'Choose which fields to show. For example --fields=name,description,license. Default is all', '');
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

		if (!empty($input->getOption('fields'))) {
			/**
			 * Validate field names.
			 *
			 * When all field names are lowercase and removed of empty spaces they must match
			 * the field names in the constant list.
			 */

			$fieldsToShow = explode(',', $input->getOption('fields'));

			$fields = [
				strtolower(str_replace(' ', '', self::NAME)) => self::NAME,
				strtolower(str_replace(' ', '', self::DESCRIPTION)) => self::DESCRIPTION,
				strtolower(str_replace(' ', '', self::LICENSE)) => self::LICENSE,
				strtolower(str_replace(' ', '', self::URL)) => self::URL,
				strtolower(str_replace(' ', '', self::STAR_COUNT)) => self::STAR_COUNT,
				strtolower(str_replace(' ', '', self::FORK_COUNT)) => self::FORK_COUNT,
				strtolower(str_replace(' ', '', self::OPEN_ISSUES)) => self::OPEN_ISSUES,
				strtolower(str_replace(' ', '', self::OPEN_PRS)) => self::OPEN_PRS,
				strtolower(str_replace(' ', '', self::HAS_CODE_OF_CONDUCT)) => self::HAS_CODE_OF_CONDUCT,
				strtolower(str_replace(' ', '', self::HAS_CUSTOM_OG_IMAGE)) => self::HAS_CUSTOM_OG_IMAGE,
				strtolower(str_replace(' ', '', self::HAS_ISSUE_TEMPLATES)) => self::HAS_ISSUE_TEMPLATES,
				strtolower(str_replace(' ', '', self::HAS_PR_TEMPLATES)) => self::HAS_PR_TEMPLATES,
				strtolower(str_replace(' ', '', self::VULNERABILITIES)) => self::VULNERABILITIES,
				strtolower(str_replace(' ', '', self::CURSOR_ID)) => self::CURSOR_ID,
			];

			$allowed = implode(', ', $fields);

			$selectedFields = [];

			foreach ($fieldsToShow as $field) {
				$cleaned = strtolower(str_replace(' ', '', $field));

				if (!isset($fields[$cleaned])) {
					$io->error("Field '{$field}' is not in the allowed fields list. Allowed list is: {$allowed}");
					return 1;
				}

				if (isset($fields[$cleaned])) {
					$selectedFields[] = $fields[$cleaned];
				}
			}
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

		$headerColumns = [
			self::NAME,
			self::DESCRIPTION,
			self::LICENSE,
			self::URL,
			self::STAR_COUNT,
			self::FORK_COUNT,
			self::OPEN_ISSUES,
			self::OPEN_PRS,
			self::HAS_CODE_OF_CONDUCT,
			self::HAS_CUSTOM_OG_IMAGE,
			self::HAS_ISSUE_TEMPLATES,
			self::HAS_PR_TEMPLATES,
			self::VULNERABILITIES,
			self::CURSOR_ID,
		];

		if (!empty($selectedFields)) {
			$headerColumns = $selectedFields;
		}

		$flippedSelectedFields = array_flip($headerColumns);

		$rowCount = 0;
		foreach ($repos as $repo) {
			if (isset($flippedSelectedFields[self::NAME])) {
				$name = $repo['repo']['name'] ?? '';
				$rows[$rowCount][] = $name;
			}

			if (isset($flippedSelectedFields[self::DESCRIPTION])) {
				$description = $repo['repo']['description'] ?? '';
				$rows[$rowCount][] = $this->trimWords($description);
			}

			if (isset($flippedSelectedFields[self::LICENSE])) {
				$license = $repo['repo']['licenseInfo']['type'] ?? '';
				$rows[$rowCount][] = $license;
			}

			if (isset($flippedSelectedFields[self::URL])) {
				$url = $repo['repo']['url'] ?? '';
				$rows[$rowCount][] = $url;
			}

			if (isset($flippedSelectedFields[self::STAR_COUNT])) {
				$starCount = $repo['repo']['stargazerCount'] ?? '';
				$rows[$rowCount][] = $starCount;
			}

			if (isset($flippedSelectedFields[self::FORK_COUNT])) {
				$forkCount = $repo['repo']['forkCount'] ?? '';
				$rows[$rowCount][] = $forkCount;
			}

			if (isset($flippedSelectedFields[self::OPEN_ISSUES])) {
				$issuesCount = $repo['repo']['openIssues']['totalCount'] ?? '';
				$rows[$rowCount][] = $issuesCount;
			}

			if (isset($flippedSelectedFields[self::OPEN_PRS])) {
				$PRCount = $repo['repo']['openPRs']['totalCount'] ?? '';
				$rows[$rowCount][] = $PRCount;
			}

			if (isset($flippedSelectedFields[self::HAS_CODE_OF_CONDUCT])) {
				$hasCodeOfConduct = !empty($repo['repo']['codeOfConduct']) ?? false;
				$rows[$rowCount][] = $hasCodeOfConduct ? '<fg=#00C851>Yes</>' : '<fg=#ff4444>No</>';
			}

			if (isset($flippedSelectedFields[self::HAS_CUSTOM_OG_IMAGE])) {
				$hasCustomOGImage = $repo['repo']['usesCustomOpenGraphImage'] ?? '';
				$rows[$rowCount][] = $hasCustomOGImage ? '<fg=#00C851>Yes</>' : '<fg=#ff4444>No</>';
			}

			if (isset($flippedSelectedFields[self::HAS_ISSUE_TEMPLATES])) {
				$hasIssueTemplates = $repo['repo']['issueTemplates'] ?? '';
				$rows[$rowCount][] = $hasIssueTemplates ? '<fg=#00C851>Yes</>' : '<fg=#ff4444>No</>';
			}

			if (isset($flippedSelectedFields[self::HAS_PR_TEMPLATES])) {
				$hasPRTemplates = $repo['repo']['pullRequestTemplates'] ?? '';
				$rows[$rowCount][] = $hasPRTemplates ? '<fg=#00C851>Yes</>' : '<fg=#ff4444>No</>';
			}

			if (isset($flippedSelectedFields[self::VULNERABILITIES])) {
				$vulnerabilityAlerts = $repo['repo']['vulnerabilityAlerts']['totalCount'] ?? 0;
				$rows[$rowCount][] = $vulnerabilityAlerts;
			}

			if (isset($flippedSelectedFields[self::CURSOR_ID])) {
				$cursorId = $repo['cursor'] ?? '';
				$rows[$rowCount][] =  $cursorId;
			}

			$rowCount++;
		}

		$io->success("Total number of repositories found for {$user}: {$totalNumber}");
		$table = new Table($output);

		$table->setHeaders($headerColumns)
			->setColumnWidths([30, 70, 15, 60, 10, 10, 11, 8, 15, 15, 15, 15, 15, 12])
			->setRows($rows)
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
			  createdAt
			  description
			  forkCount
			  licenseInfo {
				type: spdxId
				name
			  }
			  codeOfConduct {
				name
				url
			  }
			  openIssues: issues(first: 100, filterBy: {states: OPEN}) {
				totalCount
			  }
			  openPRs: pullRequests(first: 100, states: OPEN) {
				totalCount
			  }
			  stargazerCount
			  openGraphImageUrl
			  usesCustomOpenGraphImage
			  vulnerabilityAlerts(first: 100) {
				totalCount
			  }
			  issueTemplates {
				about
				body
				name
				title
			  }
			  pullRequestTemplates {
				body
				filename
			  }
			}
		  }
		}
	}
}
QUERY;
	}
}
