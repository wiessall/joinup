# Deployment workflow

We have 3 repositories that Joinup uses: the
[development repository](https://git.fpfis.tech.ec.europa.eu/digit/digit-joinup-dev),
which is where the development takes place, the
[reference repository](https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference),
which is where the releases are published, and the
[POC reference repository](https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-poc-reference),
which is where the POC environment is deployed.

For the following document, we will assume that the developer already has
appointed the development repository as `origin`, the reference repository as
`reference` and the POC reference repository as `poc-reference`.

If you have not added the reference repository as a remote, you can do so by
running the following command:

```shell
git remote add reference https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference.git
```

If you have not added the POC reference repository as a remote, you can do so
by running the following command:

```shell
git remote add poc-reference https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-poc-reference.git
```

The two main branches are the `master` branch on the reference repository and
the `develop` branch on the development repository. The master branch is the
reference for the production environment.

For this guide, we will assume that you have cloned the development repository,
and that you have a remote named `origin` that points to the development
repository, and a remote named `reference` that points to the reference
repository, but you can adopt according to your setup. After cloning the
development repository, you can add the reference repository as a remote by
running the following command:

```shell
git remote add reference https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference.git
```

The release procedure is as follows:
1. Create a release ticket in Jira. The release should be named after the
   release number. The release ticket is a placeholder for the tasks below.
2. Fetch the latest changes from the reference repository and checkout the
   `master` branch. Create a new release branch from the `master` branch. Name
   the branch `release-x.y.z`, where `x.y.z` is the version number of the
   release. Fetch the latest changes from the development repository and merge
   origin's `develop` branch into the release branch. Push the new branch to the
   reference repository.

   The commands for the above are the following:

   ```shell
   git fetch reference
   git checkout reference/master
   git checkout -b release-x.y.z
   git fetch origin
   git merge origin/develop
   git push reference release-x.y.z
   ```
3. Create a merge request from the release branch to the `master` branch. When
   the tests pass, merge the release branch into the `master` branch. This will
   automatically run the acceptance environment build. The release ticket should
   be moved to 'UAT' so that the functional team can test the release.
4. If any last minute problems are discovered during acceptance testing, these
   will be fixed in merge requests that are merged directly into the release
   branch. Of course, the normal procedure to create Jira tickets for these
   issues and move them in QA should be followed.
5. Check all resolved tickets from the previous sprint(s) and verify that they
   have a version set in the "Fix Version" field. Some tickets might be resolved
   but are not part of any Joinup release (for example: analysis/investigations,
   work done upstream, infrastructure work etc.). These should get "NOVERSION".
   The tickets can be listed by browsing all tickets in Jira and using the query
   `project = ISAICP AND Sprint = "Joinup sprint N" AND status = Resolved`.
6. List all tickets in Jira that have the "Fix Version" field set to the
   upcoming release, using the query `project = ISAICP AND fixVersion = x.y.z`.
   These tickets will be used to create the changelog in the next step.
7. Create a markdown changelog from the list of tickets. The changelog should
   contain the following sections:
   - New features
   - Improvements
   - Bug fixes
   - Security

   Do not create the changelog in a release, use the release Jira ticket
   instead.
8. When the release is approved in UAT, and the release ticket is moved to
   'Accepted', create a tag based on the `master` branch. The tag should be
   created in the reference repository. The tag should be named `vx.y.z`, where
   `x.y.z` is the version number of the release. For example, the tag for the
    1.0.0 release should be named `v1.0.0`. The description for the tag should
    be the changelog that was created in the previous step. **The creation of
    the tag will trigger the production environment build.**

## Post release procedure

After the release is done, before the post upgrade update hook removals below,
the POC environment should be updated with the release. The POC environment is
[https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-poc-reference](https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-poc-reference).
In order to trigger the release, checkout the release tag branch using the
following command:

```shell
git checkout v#.##.#
```

where `#.##.#` is the version number of the release. Then push the branch to the
POC reference repository using the following command:

```shell
git push poc-reference v#.##.#
```

In the above example, `#.##.#` is the version number of the release. The
`poc-reference` remote is the remote that points to the POC reference
repository.

Pushing the tag will automatically deploy the release to the POC environment.

### Post release update hook removals

The second step after the release is done, is to remove the deployment hooks.
Mainly, after the release is done, develop branch will start failing due to the
restriction in the deployment hook naming convention. Thus, the following steps
should be followed:

1. Move the release ticket to 'In Progress'.
2. Create a new branch based on the `master` branch from the reference
   repository named `release-x.y.z-post`. This branch will be used to clean up
   the updates that were made during the release procedure.
3. Clean any instances of the `hook_update_N`, `hook_post_update_NAME` and
   `hook_deploy_NAME` functions that were added during the release procedure.
4. Update dependencies and check for any changes required in the used patches.
5. Push the branch to the development repository and create a merge request
   from the branch to the `develop` branch. Move the release ticket to the QA
   column and allow it to be picked up by the team. When the ticket is approved,
   the team member should merge the merge request and move the release ticket to
   the 'Resolved' column.
6. Mark the release ticket as 'Released' in [Jira](https://citnet.tech.ec.europa.eu/CITnet/jira/projects/ISAICP?selectedItem=com.atlassian.jira.jira-projects-plugin%3Arelease-page&status=released-unreleased)

## Hotfix procedure

Hotfixes are the patch version of the project. For example, if the current
version is `1.0.0`, the next hotfix will be `1.0.1`. If another hotfix is needed
before the next release, the version will be `1.0.2`. The numbering of the
update functions should be named after the next release. For example, if version
`1.21.0` is released, consider that all updates named with `1021##` are already
deployed and deleted. Thus, even though the hotfix release is `1.21.1`, the
update functions should be named `1022##` and **WILL NOT** be deleted until the
next minor release (version `1.22.0`).

The hotfix procedure is as follows:
1. Create a hotfix ticket in Jira. The hotfix should be named after the hotfix
   number. The hotfix ticket is a placeholder for the tasks below.
2. Create a new branch from the `master` branch. Name the branch `release-x.y.z`,
   where `x.y.z` is the version number of the hotfix. Push the new branch to the
   reference repository.
3. Any ticket needed for the hotfix will checkout the hotfix release branch and
   will have a merge request against the hotfix release branch. When the tests
   pass, the merge request will be merged into the hotfix release branch.
4. Repeat the normal release procedure from step 5. The hotfix ticket should be
   moved to 'UAT' so that the functional team can test the hotfix.

**Important**: The hotfix does **not** have a post release procedure. The reason
for this is that the hotfix uses numbering for the update functions that is
based on the next release. This means that the update functions will be deleted
when the next release is done. It further means that the updates should not be
removed to avoid conflicts in namings.
