# GithubDeploy
Deploy your website by pushing to github.com

I use a similar process to this at work to deploy some websites and decided to make a more 
generalised one for my home use

# Requirements
git >= 2.5.0
bash
php >= 5.4.0

# Prerequisites
This assumes that you already have a git repo setup on your live server with a separate worktree 
for you htdocs directory.

It also assumes that said repo is setup to pull from github using ssh and that you have the required 
ssh/deploy keys configured  

If you do not have a properly configured repo or need help with the setup of this script i will be 
adding instructions and possibly a setup script within the next week or so


# Licence
You are free to use and modify this repo in any private or commercial project/environment you choose.
I would appreciate a credit should you do so but it is not a requirement