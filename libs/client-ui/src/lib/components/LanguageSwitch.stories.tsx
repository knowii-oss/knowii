import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { LanguageSwitch } from './language-switch';

const Story: ComponentMeta<typeof LanguageSwitch> = {
  component: LanguageSwitch,
  title: 'LanguageSwitch',
};
export default Story;

const Template: ComponentStory<typeof LanguageSwitch> = (args) => <LanguageSwitch {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
