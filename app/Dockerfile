# the different stages of this Dockerfile are meant to be built into separate images

#############################
# 		"php" stage	 		#
#############################
# The base stage for all our stages

# pull official base image
FROM node:13.12.0-alpine AS dashboard_app

# Copy env files
# set working directory
WORKDIR /app

# add `/app/node_modules/.bin` to $PATH
ENV PATH /app/node_modules/.bin:$PATH


# install app dependencies
COPY package.json ./
COPY package-lock.json ./
RUN npm install

# add app
COPY . ./

# start app
CMD ["npm", "start"]
