import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SocialSignInButton } from './SocialSignInButton';

const Story: ComponentMeta<typeof SocialSignInButton> = {
  component: SocialSignInButton,
  title: 'SocialSignInButton',
};
export default Story;

const Template: ComponentStory<typeof SocialSignInButton> = (args) => <SocialSignInButton {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
